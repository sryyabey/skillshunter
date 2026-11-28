<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Skill;
use App\Support\SkillSourceReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SkillDiscoverySourceController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'api_version' => 1,
            'platforms' => ['github', 'gitlab', 'bitbucket'],
            'sources' => DB::table('skill_discovered_sources')->count(),
            'pending_review' => Skill::whereIn('id', DB::table('skill_discovered_sources')->select('skill_id'))
                ->whereNull('ai_reviewed_at')->whereNull('ai_review_rejected_at')->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_url' => ['required', 'string', 'url:https', 'max:2048'],
            'source_commit' => ['required', 'regex:/\A[0-9a-f]{40}\z/'],
            'source_markdown' => ['required', 'string', 'max:100000', 'not_regex:/\x00/'],
        ]);
        $reference = SkillSourceReference::parse($data['source_url'], $data['source_commit']);
        if ($reference === null) {
            throw ValidationException::withMessages(['source_url' => 'Use a pinned GitHub, GitLab.com or Bitbucket.org SKILL.md URL.']);
        }
        $hash = SkillSourceReference::fingerprint($data['source_markdown']);
        $identity = SkillSourceReference::identity($reference);

        return DB::transaction(function () use ($data, $reference, $hash, $identity): JsonResponse {
            $existing = DB::table('skill_discovered_sources')->where('identity_hash', $identity)->orWhere('content_hash', $hash)->first();
            if ($existing) {
                return response()->json(['data' => ['status' => $existing->content_hash === $hash ? 'existing' : 'changed', 'skill_id' => $existing->skill_id]]);
            }
            $knownSkill = Skill::query()->where('source_identity_hash', $identity)->first();
            if ($knownSkill && $knownSkill->source_content_hash !== $hash) {
                return response()->json(['data' => ['status' => 'changed', 'skill_id' => $knownSkill->id]]);
            }
            $known = $knownSkill?->id ?? Skill::where('source_content_hash', $hash)->value('id');
            // Reserve both unique keys before creating a draft; concurrent requests cannot create duplicates.
            $inserted = DB::table('skill_discovered_sources')->insertOrIgnore([
                'identity_hash' => $identity, 'content_hash' => $hash, ...$reference,
                'source_url' => $data['source_url'], 'source_commit' => $data['source_commit'],
                'skill_id' => $known, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (! $inserted) {
                return response()->json(['data' => ['status' => 'existing']]);
            }
            if ($known !== null) {
                return response()->json(['data' => ['status' => 'existing', 'skill_id' => $known]]);
            }
            $category = Category::where('slug', 'software-development')->firstOrFail();
            $folder = basename(dirname($reference['path']));
            $title = $folder === '.' ? basename($reference['repository']) : $folder;
            $skill = Skill::create([
                ...$data, 'category_id' => $category->id,
                'slug' => 'discovered-'.substr($identity, 0, 48),
                'title' => Str::limit(Str::headline($title), 255, ''),
                'summary' => 'Discovered source awaiting ControllerAI review.',
                'instructions' => '', 'is_published' => false,
            ]);
            DB::table('skill_discovered_sources')->where('identity_hash', $identity)->update(['skill_id' => $skill->id]);

            return response()->json(['data' => ['status' => 'created', 'skill_id' => $skill->id]], 201);
        }, attempts: 3);
    }
}
