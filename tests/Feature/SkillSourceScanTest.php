<?php

namespace Tests\Feature;

use App\Filament\Manage\Resources\SkillSourceFiles\Pages\ManageSkillSourceFiles;
use App\Models\Category;
use App\Models\Skill;
use App\Models\SkillSourceFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SkillSourceScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_skips_catalog_and_duplicate_sources_and_claims_only_the_pinned_job(): void
    {
        $category = Category::factory()->create(['slug' => 'software-development']);
        $skill = Skill::factory()->for($category)->published()->create();
        $commit = str_repeat('a', 40);
        $known = str_repeat('b', 40);
        $fresh = str_repeat('c', 40);
        $repository = DB::table('skill_source_repositories')->insertGetId([
            'github_id' => 123, 'full_name' => 'example/skills', 'default_branch' => 'main', 'last_scanned_commit' => $commit,
        ]);

        foreach ([
            ['existing/SKILL.md', $known, $skill->id],
            ['existing-copy/SKILL.md', $known, null],
            ['new/SKILL.md', $fresh, null],
            ['new-copy/SKILL.md', $fresh, null],
        ] as [$path, $blob, $skillId]) {
            DB::table('skill_source_files')->insert([
                'skill_source_repository_id' => $repository, 'path' => $path, 'path_hash' => hash('sha256', $path),
                'commit_sha' => $commit, 'blob_sha' => $blob, 'skill_id' => $skillId,
            ]);
        }

        $this->artisan('skills:queue-sources')->expectsOutput('Preview: 1 source(s).')->assertSuccessful();
        $this->assertDatabaseCount('skill_discovery_jobs', 0);
        $this->artisan('skills:queue-sources', ['--apply' => true])->expectsOutput('Queued 1 source(s).')->assertSuccessful();
        $this->artisan('skills:queue-sources', ['--apply' => true])->expectsOutput('Queued 0 source(s).')->assertSuccessful();
        $this->assertDatabaseCount('skill_discovery_jobs', 1);

        $token = 'inventory-integration-test-token-at-least-32-characters';
        config(['skill-integration.token' => $token]);
        $this->withToken($token)->postJson('/api/v1/discovery-jobs/claim', [])->assertOk()->assertJsonPath('data', null);
        $this->postJson('/api/v1/discovery-jobs/claim', ['source_inventory' => true])->assertOk()
            ->assertJsonPath('data.source.path', 'new/SKILL.md')
            ->assertJsonPath('data.source.commit', $commit)->assertJsonPath('data.source.blob', $fresh);
    }

    public function test_queue_all_includes_every_eligible_file_across_repositories(): void
    {
        Category::factory()->create(['slug' => 'software-development']);
        $commit = str_repeat('a', 40);
        foreach ([101, 102] as $githubId) {
            $repository = DB::table('skill_source_repositories')->insertGetId([
                'github_id' => $githubId, 'full_name' => "example/repo-{$githubId}", 'default_branch' => 'main',
                'last_scanned_commit' => $commit,
            ]);
            for ($number = 0; $number < 30; $number++) {
                $path = "{$number}/SKILL.md";
                DB::table('skill_source_files')->insert([
                    'skill_source_repository_id' => $repository, 'path' => $path, 'path_hash' => hash('sha256', $path),
                    'commit_sha' => $commit, 'blob_sha' => sha1("{$githubId}/{$path}"),
                ]);
            }
        }

        $this->artisan('skills:queue-sources', ['--all' => true])->expectsOutput('Preview: 60 source(s).')->assertSuccessful();
        $this->assertDatabaseCount('skill_discovery_jobs', 0);
        $this->artisan('skills:queue-sources', ['--all' => true, '--apply' => true])
            ->expectsOutput('Queued 60 source(s).')->assertSuccessful();
        $this->artisan('skills:queue-sources', ['--all' => true, '--apply' => true])
            ->expectsOutput('Queued 0 source(s).')->assertSuccessful();
        $this->assertDatabaseCount('skill_discovery_jobs', 60);
    }

    public function test_filament_displays_inventory_to_users_with_discovery_view_permission(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole(Role::create(['name' => 'panel_user']));
        $this->actingAs($editor);
        $repositories = route('filament.manage.resources.skill-source-repositories.index');
        $files = route('filament.manage.resources.skill-source-files.index');

        $this->get($repositories)->assertForbidden();
        $this->get($files)->assertForbidden();

        $editor->givePermissionTo(Permission::create(['name' => 'ViewAny:SkillDiscoveryJob']));
        $commit = str_repeat('a', 40);
        $repository = DB::table('skill_source_repositories')->insertGetId([
            'github_id' => 123, 'full_name' => 'example/skills', 'default_branch' => 'main', 'last_scanned_commit' => $commit,
        ]);
        DB::table('skill_source_files')->insert([
            'skill_source_repository_id' => $repository, 'path' => 'demo/SKILL.md', 'path_hash' => hash('sha256', 'demo/SKILL.md'),
            'commit_sha' => $commit, 'blob_sha' => str_repeat('b', 40),
        ]);

        $this->get($repositories)->assertOk()->assertSee('example/skills')->assertSee('Active files');
        $this->get($files)->assertOk()->assertSee('demo/SKILL.md')->assertSee('Unlinked')->assertSee('No job');

        $unqueued = SkillSourceFile::where('path', 'demo/SKILL.md')->sole();
        $queuedId = DB::table('skill_source_files')->insertGetId([
            'skill_source_repository_id' => $repository, 'path' => 'queued/SKILL.md', 'path_hash' => hash('sha256', 'queued/SKILL.md'),
            'commit_sha' => $commit, 'blob_sha' => str_repeat('c', 40),
        ]);
        $queued = SkillSourceFile::findOrFail($queuedId);
        $category = Category::factory()->create();
        DB::table('skill_discovery_jobs')->insert([
            'topic' => 'Queued source', 'category_id' => $category->id, 'source_file_id' => $queuedId,
        ]);

        Livewire::test(ManageSkillSourceFiles::class)
            ->filterTable('draft_job', 'queued')->assertCanSeeTableRecords([$queued])->assertCanNotSeeTableRecords([$unqueued])
            ->filterTable('draft_job', 'none')->assertCanSeeTableRecords([$unqueued])->assertCanNotSeeTableRecords([$queued]);
    }

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
