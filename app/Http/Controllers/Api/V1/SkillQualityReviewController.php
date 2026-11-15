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
use Illuminate\Support\Facades\Http;
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
            $skill = Skill::query()->whereNull('ai_reviewed_at')
                ->whereLike('source_url', 'https://github.com/%/blob/%/SKILL.md')
                ->where(fn (Builder $query) => $query->whereNull('ai_review_lease_until')->orWhere('ai_review_lease_until', '<=', now()))
                ->orderBy('created_at')->orderBy('id')->lockForUpdate()->first();

            if (! $skill) {
                return response()->json(['api_version' => 2, 'data' => null]);
            }

            $token = Str::random(64);
            Skill::query()->whereKey($skill->id)->toBase()->update([
                'ai_review_lease_hash' => $this->leaseHash($skill, $token),
                'ai_review_lease_until' => now()->addMinutes(45),
            ]);

            return response()->json(['api_version' => 2, 'data' => [
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
            'source_revision' => ['sometimes', 'required', 'array:url,commit'],
            'source_revision.url' => ['required_with:source_revision', 'string', 'max:2048', 'url:https'],
            'source_revision.commit' => ['required_with:source_revision', 'regex:/\A[0-9a-f]{40}\z/'],
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
            'changes.sample_output' => ['nullable', 'string', 'max:30000'],
        ]);

        $revision = $data['source_revision'] ?? null;
        $sourceMarkdown = null;
        if ($revision !== null) {
            $record = Skill::query()->findOrFail($skill);
            $this->assertLease($record, $data['lease_token']);
            $sourceMarkdown = $this->fetchSourceRevision($record, $revision);
        }

        return DB::transaction(function () use ($skill, $data, $revision, $sourceMarkdown): JsonResponse {
            $record = Skill::query()->lockForUpdate()->findOrFail($skill);
            $this->assertLease($record, $data['lease_token']);

            $changes = $data['changes'];
            $source = str_replace("\r\n", "\n", $sourceMarkdown ?? $record->source_markdown ?? '');
            if (blank($source) || preg_match('/^ {0,3}#{1,6} Insert instructions below[.!]?[ \t]*$/mi', $source)) {
                throw ValidationException::withMessages(['source_revision' => 'A usable SKILL.md is required to complete review.']);
            }
            foreach (['setup_tip', 'sample_input', 'sample_output'] as $field) {
                if (isset($changes[$field]) && ! str_contains($source, str_replace("\r\n", "\n", $changes[$field]))) {
                    throw ValidationException::withMessages(["changes.{$field}" => 'Use a verbatim excerpt from SKILL.md.']);
                }
            }
            if (array_key_exists('sample_input', $changes) && $changes['sample_input'] !== $record->sample_input
                && ! array_key_exists('sample_output', $changes) && filled($record->sample_output)) {
                throw ValidationException::withMessages(['changes.sample_output' => 'Clear or replace the existing output when changing its input.']);
            }

            $diff = [];
            if ($revision !== null) {
                $record->fill([
                    'source_url' => $revision['url'],
                    'source_commit' => $revision['commit'],
                    'source_markdown' => $sourceMarkdown,
                ]);
                foreach (['source_url', 'source_commit', 'source_markdown'] as $field) {
                    if ($record->isDirty($field)) {
                        $diff[] = ['field' => $field, 'before' => $record->getOriginal($field), 'after' => $record->{$field}];
                    }
                }
            }

            $before = $record->only(self::EDITORIAL_FIELDS);
            $record->fill(Arr::only($changes, self::EDITORIAL_FIELDS));
            foreach (self::EDITORIAL_FIELDS as $field) {
                if ($record->isDirty($field)) {
                    $diff[] = ['field' => $field, 'before' => $before[$field], 'after' => $record->{$field}];
                }
            }
            if (isset($changes['category_slug'])) {
                $category = Category::where('slug', $changes['category_slug'])->firstOrFail();
                if ($record->category_id !== $category->id) {
                    $diff[] = ['field' => 'category', 'before' => $record->category->name, 'after' => $category->name];
                    $record->category_id = $category->id;
                }
            }
            $wasPublished = $record->is_published;
            if ($record->isDirty(['source_url', 'source_commit', 'source_markdown'])) {
                $record->save();
            }
            $record->forceFill([
                'ai_review_details' => [
                    'source_url' => $record->source_url,
                    'source_commit' => $record->source_commit,
                    'was_published' => $wasPublished,
                    'changes' => $diff,
                ],
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

    private function assertLease(Skill $record, string $token): void
    {
        abort_unless($record->ai_review_lease_until?->isFuture()
            && hash_equals((string) $record->ai_review_lease_hash, $this->leaseHash($record, $token)),
            409, 'The review lease expired or the skill changed during review.');
    }

    private function fetchSourceRevision(Skill $record, array $revision): string
    {
        $pattern = '~\Ahttps://github\.com/([\w.-]+/[\w.-]+)/blob/([^/?#]+)/([^?#]+)\z~';
        if (! preg_match($pattern, $record->source_url ?? '', $original)
            || ! preg_match($pattern, $revision['url'], $target)
            || strcasecmp($original[1], $target[1]) !== 0
            || rawurldecode($original[3]) !== rawurldecode($target[3])
            || $target[2] !== $revision['commit']
            || ($record->source_commit && $record->source_commit !== $revision['commit'])) {
            throw ValidationException::withMessages(['source_revision.url' => 'Use the same GitHub source file at its verified commit.']);
        }

        $segments = explode('/', $target[1].'/'.rawurldecode($target[3]));
        if (array_intersect($segments, ['', '.', '..']) || strcasecmp(end($segments), 'SKILL.md') !== 0) {
            throw ValidationException::withMessages(['source_revision.url' => 'Invalid SKILL.md path.']);
        }

        $repo = implode('/', array_splice($segments, 0, 2));
        $path = implode('/', array_map('rawurlencode', $segments));
        $response = Http::timeout(15)->withoutRedirecting()->withOptions(['stream' => true])
            ->get('https://raw.githubusercontent.com/'.$repo.'/'.$revision['commit'].'/'.$path);
        abort_unless($response->ok(), 502, 'GitHub source could not be verified.');

        $stream = $response->toPsrResponse()->getBody();
        $source = '';
        try {
            while (! $stream->eof() && strlen($source) <= 400000) {
                $source .= $stream->read(8192);
            }
        } finally {
            $stream->close();
        }
        if (blank($source) || ! mb_check_encoding($source, 'UTF-8') || mb_strlen($source) > 100000
            || str_contains($source, "\0")
            || preg_match('/^ {0,3}#{1,6} Insert instructions below[.!]?[ \t]*$/mi', $source)) {
            throw ValidationException::withMessages(['source_revision.url' => 'The pinned SKILL.md has no usable instructions.']);
        }

        return $source;
    }

    private function leaseHash(Skill $skill, string $token): string
    {
        $snapshot = Arr::except($skill->getRawOriginal(), ['ai_review_lease_hash', 'ai_review_lease_until']);

        return hash_hmac('sha256', serialize($snapshot), $token);
    }
}
