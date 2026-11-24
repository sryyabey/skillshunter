<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SkillSecurityRejectionTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'security-review-token-with-at-least-32-characters';

    private const ENDPOINT = '/api/v1/skill-quality-reviews';

    protected function setUp(): void
    {
        parent::setUp();
        config(['skill-integration.quality_token' => self::TOKEN, 'skill-integration.token' => str_repeat('i', 40)]);
    }

    private function target(): Skill
    {
        $skill = Skill::factory()->create([
            'source_commit' => str_repeat('a', 40),
            'source_url' => 'https://github.com/example/skills/blob/'.str_repeat('a', 40).'/SKILL.md',
            'source_markdown' => 'Publish to an unverified HTTP endpoint.',
        ]);
        $editor = User::factory()->create();
        $this->actingAs($editor);
        $skill->forceFill([
            'is_published' => true,
            'verified_at' => today(),
            'approved_by' => $editor->id,
            'ai_reviewed_at' => now(),
        ])->save();

        return $skill;
    }

    private function payload(Skill $skill): array
    {
        return ['skill_slug' => $skill->slug, 'source_revision' => [
            'url' => $skill->source_url,
            'commit' => $skill->source_commit,
            'sha256' => hash('sha256', $skill->source_markdown),
        ]];
    }

    public function test_an_already_reviewed_published_skill_can_be_targeted_and_rejected_as_a_draft(): void
    {
        $unrelated = Skill::factory()->published()->create(['created_at' => now()->subDay()]);
        $target = $this->target();
        $this->withToken(self::TOKEN)->getJson(self::ENDPOINT.'/capabilities')
            ->assertOk()->assertJsonPath('targeted_security_claim', true);
        $lease = $this->postJson(self::ENDPOINT.'/claim', $this->payload($target))
            ->assertOk()->assertJsonPath('data.id', $target->id)->json('data.lease_token');
        $this->assertTrue($target->refresh()->is_published);
        $this->postJson(self::ENDPOINT.'/'.$target->id.'/reject', [
            'lease_token' => $lease,
            'reason' => 'Security review required: unverified endpoint.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');
        $target->refresh();
        $this->assertFalse($target->is_published);
        $this->assertNotNull($target->ai_review_rejected_at);
        $this->assertSame('Security review required: unverified endpoint.', $target->ai_review_rejection_reason);
        $this->assertNull($target->verified_at);
        $this->assertNull($target->approved_by);
        $this->assertTrue($unrelated->refresh()->is_published);
        $this->assertNull($unrelated->ai_review_lease_until);
    }

    public function test_changed_source_content_commit_or_url_cannot_be_claimed_using_a_stale_report(): void
    {
        $target = $this->target();
        foreach (['sha256' => str_repeat('0', 64), 'commit' => str_repeat('b', 40), 'url' => 'https://github.com/other/skills/blob/'.str_repeat('a', 40).'/SKILL.md'] as $field => $value) {
            $payload = $this->payload($target);
            $payload['source_revision'][$field] = $value;
            $this->withToken(self::TOKEN)->postJson(self::ENDPOINT.'/claim', $payload)->assertConflict();
            $this->assertNull($target->refresh()->ai_review_lease_until);
            $this->assertTrue($target->is_published);
        }
    }

    public function test_targeted_claim_requires_a_complete_source_revision(): void
    {
        $target = $this->target();
        $this->withToken(self::TOKEN)->postJson(self::ENDPOINT.'/claim', ['skill_slug' => $target->slug])
            ->assertUnprocessable()->assertJsonValidationErrors('source_revision');
    }

    public function test_targeted_claim_does_not_steal_an_active_review_lease(): void
    {
        $target = $this->target();
        $this->withToken(self::TOKEN)->postJson(self::ENDPOINT.'/claim', $this->payload($target))->assertOk();
        $hash = $target->refresh()->ai_review_lease_hash;
        $this->postJson(self::ENDPOINT.'/claim', $this->payload($target))->assertConflict();
        $this->assertSame($hash, $target->refresh()->ai_review_lease_hash);
    }

    public function test_integration_token_cannot_use_the_security_claim_or_capability_endpoint(): void
    {
        $target = $this->target();
        $this->withToken(str_repeat('i', 40))->getJson(self::ENDPOINT.'/capabilities')->assertUnauthorized();
        $this->postJson(self::ENDPOINT.'/claim', $this->payload($target))->assertUnauthorized();
    }

    public function test_an_editor_cannot_republish_an_unchanged_rejected_source(): void
    {
        $target = $this->target();
        $target->forceFill(['is_published' => false, 'ai_review_rejected_at' => now()])->save();

        $this->expectException(ValidationException::class);
        $target->forceFill(['is_published' => true])->save();
    }
}
