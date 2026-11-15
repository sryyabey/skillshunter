<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillQualityReviewController extends Controller
{
    private const EDITORIAL_FIELDS = [
        'title', 'summary', 'editor_note', 'use_cases', 'seo_title', 'seo_description',
        'seo_keywords', 'setup_tip', 'sample_input', 'sample_output',
    ];

    public function claim(): JsonResponse
    {
        return DB::transaction(function (): JsonResponse {
            $skill = Skill::query()->whereNull('ai_reviewed_at')->whereNotNull('source_markdown')
                ->where('source_markdown', '!=', '')
                ->where(fn (Builder $query) => $query->whereNull('ai_review_lease_until')->orWhere('ai_review_lease_until', '<=', now()))
                ->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();

            if (! $skill) {
                return response()->json(['data' => null]);
            }

            $token = Str::random(64);
            Skill::query()->whereKey($skill->id)->toBase()->update([
                'ai_review_lease_hash' => $this->leaseHash($skill, $token),
                'ai_review_lease_until' => now()->addMinutes(45),
            ]);

            return response()->json(['data' => [
                'id' => $skill->id,
                'slug' => $skill->slug,
                'lease_token' => $token,
                'is_published' => $skill->is_published,
                'source_url' => $skill->source_url,
                'source_commit' => $skill->source_commit,
                'source_markdown' => $skill->source_markdown,
                'category_slug' => $skill->category->slug,
                'categories' => Category::orderBy('name')->get(['slug', 'name']),
                ...$skill->only(self::EDITORIAL_FIELDS),
            ]]);
        }, attempts: 3);
    }

    public function complete(Request $request, int $skill): JsonResponse
    {
        $data = $request->validate([
            'lease_token' => ['required', 'string', 'size:64'],
            'changes' => ['present', 'array:'.implode(',', [...self::EDITORIAL_FIELDS, 'category_slug'])],
            'changes.title' => ['sometimes', 'required', 'string', 'max:255'],
            'changes.summary' => ['sometimes', 'required', 'string', 'max:4000'],
            'changes.category_slug' => ['sometimes', 'required', 'string', 'exists:categories,slug'],
            'changes.editor_note' => ['nullable', 'string', 'max:4000'],
            'changes.use_cases' => ['nullable', 'array', 'list', 'max:20'],
            'changes.use_cases.*' => ['required', 'string', 'max:300'],
            'changes.seo_title' => ['nullable', 'string', 'max:70'],
            'changes.seo_description' => ['nullable', 'string', 'max:170'],
            'changes.seo_keywords' => ['nullable', 'array', 'list', 'max:12'],
            'changes.seo_keywords.*' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'changes.setup_tip' => ['nullable', 'string', 'max:4000'],
            'changes.sample_input' => ['required_with:changes.sample_output', 'nullable', 'string', 'max:30000'],
            'changes.sample_output' => ['required_with:changes.sample_input', 'nullable', 'string', 'max:30000'],
        ]);

        return DB::transaction(function () use ($skill, $data): JsonResponse {
            $record = Skill::query()->lockForUpdate()->findOrFail($skill);
            abort_unless($record->ai_review_lease_until?->isFuture()
                && hash_equals((string) $record->ai_review_lease_hash, $this->leaseHash($record, $data['lease_token'])),
                409, 'The review lease expired or the skill changed during review.');

            $changes = $data['changes'];
            $source = str_replace("\r\n", "\n", $record->source_markdown);
            foreach (['setup_tip', 'sample_input', 'sample_output'] as $field) {
                if (isset($changes[$field]) && ! str_contains($source, str_replace("\r\n", "\n", $changes[$field]))) {
                    throw ValidationException::withMessages(["changes.{$field}" => 'Use a verbatim excerpt from SKILL.md.']);
                }
            }
            if (array_key_exists('sample_input', $changes) !== array_key_exists('sample_output', $changes)) {
                throw ValidationException::withMessages(['changes.sample_input' => 'Submit or clear both example fields together.']);
            }

            $record->fill(Arr::only($changes, self::EDITORIAL_FIELDS));
            if (isset($changes['category_slug'])) {
                $record->category_id = Category::where('slug', $changes['category_slug'])->firstOrFail()->id;
            }
            $record->forceFill([
                'is_published' => false,
                'verified_at' => null,
                'approved_by' => null,
                'ai_reviewed_at' => now(),
                'ai_review_lease_hash' => null,
                'ai_review_lease_until' => null,
            ])->save();

            return response()->json(['data' => [
                'id' => $record->id,
                'ai_reviewed' => true,
                'is_published' => false,
                'ai_reviewed_at' => $record->ai_reviewed_at->toISOString(),
            ]]);
        }, attempts: 3);
    }

    private function leaseHash(Skill $skill, string $token): string
    {
        $snapshot = Arr::except($skill->getRawOriginal(), ['ai_review_lease_hash', 'ai_review_lease_until']);

        return hash_hmac('sha256', serialize($snapshot), $token);
    }
}
