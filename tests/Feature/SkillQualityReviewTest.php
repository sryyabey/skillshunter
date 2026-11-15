<?php

namespace Tests\Feature;

use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ]);
        $new = Skill::factory()->create(['source_markdown' => 'New instructions.']);

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
        $this->postJson(self::ENDPOINT.'/claim')->assertOk()->assertJsonPath('data', null);
        $this->withToken(self::TOKEN)->getJson('/api/v1/skills')->assertUnauthorized();
    }

    public function test_quality_review_cannot_overwrite_a_concurrent_editor_change(): void
    {
        $skill = Skill::factory()->published()->create(['source_markdown' => 'Source text.']);
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
