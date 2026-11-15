<?php

namespace Tests\Feature;

use App\Filament\Manage\Resources\Skills\Pages\ManageSkills;
use App\Models\Skill;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SkillQualityReviewTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'quality-review-test-token-at-least-32-characters';

    private const ENDPOINT = '/api/v1/skill-quality-reviews';

    protected function setUp(): void
    {
        parent::setUp();
        config(['skill-integration.quality_token' => self::TOKEN, 'skill-integration.token' => str_repeat('i', 40)]);
    }

    public function test_worker_claims_oldest_source_and_saves_changes_as_reviewed_draft(): void
    {
        Skill::factory()->create(['created_at' => now()->subDays(3)]);
        $old = Skill::factory()->published()->create([
            'source_markdown' => "## Setup\nInstall Example CLI.\n",
            'created_at' => now()->subDays(2),
            'verified_at' => today(),
            'editor_note' => 'Previous editorial note.',
            'source_url' => 'https://github.com/example/skills/blob/main/SKILL.md',
        ]);
        $new = Skill::factory()->create(['source_markdown' => 'New instructions.', 'source_url' => 'https://github.com/example/skills/blob/main/new/SKILL.md']);

        $this->withToken(str_repeat('i', 40))->postJson(self::ENDPOINT.'/claim')->assertUnauthorized();

        $this->withToken(self::TOKEN);
        $path = self::ENDPOINT.'/'.$old->id;
        $lease = $this->postJson(self::ENDPOINT.'/claim')->assertOk()->assertJsonPath('data.id', $old->id)
            ->json('data.lease_token');
        $this->postJson(self::ENDPOINT.'/claim')->assertOk()->assertJsonPath('data.id', $new->id);
        $this->assertTrue($old->refresh()->is_published);

        $this->patchJson($path, ['lease_token' => $lease, 'changes' => ['setup_tip' => 'Run invented.py']])
            ->assertUnprocessable()->assertJsonValidationErrors('changes.setup_tip');
        $this->patchJson($path, ['lease_token' => $lease, 'changes' => ['sample_input' => null]])
            ->assertUnprocessable()->assertJsonValidationErrors('changes.sample_input');
        $this->patchJson($path, ['lease_token' => $lease, 'changes' => [
            'editor_note' => 'The setup requires Example CLI.',
            'setup_tip' => 'Install Example CLI.',
        ]])->assertOk()->assertJsonPath('data.ai_reviewed', true)->assertJsonPath('data.is_published', false);

        $old->refresh();
        $this->assertSame('The setup requires Example CLI.', $old->editor_note);
        $this->assertFalse($old->is_published);
        $this->assertNull($old->verified_at);
        $this->assertNotNull($old->ai_reviewed_at);
        $this->assertSame(true, $old->ai_review_details['was_published']);
        $this->assertSame($old->source_url, $old->ai_review_details['source_url']);
        $this->assertSame([
            ['field' => 'editor_note', 'before' => 'Previous editorial note.', 'after' => 'The setup requires Example CLI.'],
            ['field' => 'setup_tip', 'before' => null, 'after' => 'Install Example CLI.'],
        ], $old->ai_review_details['changes']);
        $this->postJson(self::ENDPOINT.'/claim')->assertOk()->assertJsonPath('data', null);
        $this->withToken(self::TOKEN)->getJson('/api/v1/skills')->assertUnauthorized();

        $editor = User::factory()->create();
        $editor->assignRole(Role::create(['name' => 'panel_user']));
        $editor->givePermissionTo(Permission::create(['name' => 'ViewAny:Skill']));
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $page = Livewire::test(ManageSkills::class)
            ->assertTableActionHidden('aiReviewDetails', $new)
            ->assertTableActionVisible('aiReviewDetails', $old)
            ->mountTableAction('aiReviewDetails', $old);

        $modal = json_encode($page->effects, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $this->assertStringContainsString('Previous editorial note.', $modal);
        $this->assertStringContainsString('The setup requires Example CLI.', $modal);
        $this->assertStringContainsString($old->source_url, $modal);
    }

    public function test_review_can_fill_missing_instructions_from_the_verified_github_revision(): void
    {
        $commit = str_repeat('a', 40);
        $skill = Skill::factory()->published()->create([
            'source_url' => 'https://github.com/example/skills/blob/main/SKILL.md',
        ]);
        $raw = "## Setup\nInstall Example CLI.\n";
        Http::preventStrayRequests();
        Http::fake(["https://raw.githubusercontent.com/example/skills/{$commit}/SKILL.md" => Http::response($raw)]);
        $this->withToken(self::TOKEN);
        $lease = $this->postJson(self::ENDPOINT.'/claim')->assertOk()->assertJsonPath('data.id', $skill->id)->json('data.lease_token');
        $this->patchJson(self::ENDPOINT.'/'.$skill->id, [
            'lease_token' => $lease,
            'changes' => ['setup_tip' => 'Install Example CLI.'],
            'source_revision' => ['url' => "https://github.com/example/skills/blob/{$commit}/SKILL.md", 'commit' => $commit],
        ])->assertOk()->assertJsonPath('data.is_published', false);
        $skill->refresh();
        $this->assertSame($raw, $skill->source_markdown);
        $this->assertSame($commit, $skill->source_commit);
        $this->assertTrue($skill->ai_review_details['was_published']);
        $this->assertNotNull($skill->ai_reviewed_at);
    }

    public function test_quality_review_cannot_overwrite_a_concurrent_editor_change(): void
    {
        $skill = Skill::factory()->published()->create(['source_markdown' => 'Source text.', 'source_url' => 'https://github.com/example/skills/blob/main/SKILL.md']);
        $this->withToken(self::TOKEN);
        $lease = $this->postJson(self::ENDPOINT.'/claim')->assertOk()->json('data.lease_token');

        $skill->update(['summary' => 'Editor changed the summary.']);
        $this->patchJson(self::ENDPOINT.'/'.$skill->id, [
            'lease_token' => $lease,
            'changes' => ['summary' => 'Worker edit.'],
        ])->assertConflict();

        $this->assertTrue($skill->refresh()->is_published);
        $this->assertSame('Editor changed the summary.', $skill->summary);
    }
}
