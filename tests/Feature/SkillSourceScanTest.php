<?php

namespace Tests\Feature;

use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SkillSourceScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_and_repeated_import_reconcile_existing_catalog_records(): void
    {
        $commit = str_repeat('a', 40);
        $blob = str_repeat('b', 40);
        $skill = Skill::factory()->published()->create([
            'source_url' => 'https://github.com/Example/Skills/blob/main/skills/demo/SKILL.md',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://skills.sh/api/v1/skills/curated' => Http::response(['data' => [
                ['skills' => [
                    ['source' => 'example/skills', 'sourceType' => 'github'],
                    ['source' => 'example/fork', 'sourceType' => 'github', 'isDuplicate' => true],
                ]],
            ]]),
            'https://api.github.com/repos/example/skills' => Http::response([
                'id' => 123, 'full_name' => 'Example/Skills', 'default_branch' => 'main',
                'private' => false, 'archived' => false,
            ]),
            'https://api.github.com/repos/example/skills/commits/main' => Http::response(['sha' => $commit]),
            'https://api.github.com/repos/example/skills/git/trees/*' => Http::sequence()
                ->push(['truncated' => false, 'tree' => [
                    ['path' => 'skills/demo/SKILL.md', 'type' => 'blob', 'sha' => $blob],
                    ['path' => 'extra/SKILL.md', 'type' => 'blob', 'sha' => $blob],
                ]])
                ->push(['truncated' => false, 'tree' => [
                    ['path' => 'skills/demo/SKILL.md', 'type' => 'blob', 'sha' => $blob],
                    ['path' => 'extra/SKILL.md', 'type' => 'blob', 'sha' => $blob],
                ]])
                ->push(['truncated' => false, 'tree' => [
                    ['path' => 'skills/demo/SKILL.md', 'type' => 'blob', 'sha' => $blob],
                ]]),
        ]);

        $this->artisan('skills:scan-sources', ['--curated' => true])->assertSuccessful();
        $this->assertDatabaseCount('skill_source_repositories', 0);

        for ($run = 0; $run < 2; $run++) {
            $this->artisan('skills:scan-sources', ['--curated' => true, '--apply' => true])->assertSuccessful();
        }
        $this->assertDatabaseCount('skill_source_repositories', 1);
        $this->assertDatabaseCount('skill_source_files', 2);
        $this->assertDatabaseCount('skills', 1);
        $this->assertDatabaseHas('skill_source_files', [
            'skill_id' => $skill->id, 'path' => 'skills/demo/SKILL.md', 'commit_sha' => $commit, 'removed_at' => null,
        ]);
        $this->assertDatabaseMissing('skill_source_files', ['path' => 'extra/SKILL.md', 'removed_at' => null]);
    }
}
