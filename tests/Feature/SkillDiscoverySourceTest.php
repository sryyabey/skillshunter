<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillDiscoverySourceTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'finder-integration-test-token-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();
        config(['skill-integration.token' => $this->token, 'skill-integration.quality_token' => str_repeat('q', 40)]);
        Category::factory()->create(['slug' => 'software-development']);
    }

    private function source(string $host = 'github.com', string $marker = 'blob', string $text = "# Useful skill\nDo a task."): array
    {
        return ['source_url' => 'https://'.$host.'/example/skills/'.$marker.'/'.str_repeat('a', 40).'/example/SKILL.md',
            'source_commit' => str_repeat('a', 40), 'source_markdown' => $text];
    }

    public function test_discovery_requires_authentication_and_a_pinned_trusted_source(): void
    {
        $this->postJson('/api/v1/discovery-sources', $this->source())->assertUnauthorized();
        $this->withToken($this->token)->postJson('/api/v1/discovery-sources', $this->source('evil.example'))->assertUnprocessable();
        $data = $this->source();
        $data['source_url'] = str_replace('example/SKILL.md', '../SKILL.md', $data['source_url']);
        $this->postJson('/api/v1/discovery-sources', $data)->assertUnprocessable();
        $this->assertDatabaseCount('skills', 0);
    }

    public function test_cross_platform_duplicates_and_existing_catalog_content_do_not_create_drafts(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/discovery-sources', $this->source())->assertCreated();
        $this->postJson('/api/v1/discovery-sources', $this->source('gitlab.com', '-/blob', "# Useful skill\r\nDo a task.\r\n"))
            ->assertOk()->assertJsonPath('data.status', 'existing');
        $this->assertDatabaseCount('skills', 1);
        $this->assertDatabaseHas('skills', ['is_published' => false, 'ai_reviewed_at' => null]);
        Skill::factory()->create(['source_markdown' => '# Already catalogued']);
        $this->postJson('/api/v1/discovery-sources', $this->source('bitbucket.org', 'src', '# Already catalogued'))
            ->assertOk()->assertJsonPath('data.status', 'existing');
        $this->assertDatabaseCount('skills', 2);
    }

    public function test_changed_sources_do_not_overwrite_an_existing_draft(): void
    {
        $this->withToken($this->token)->postJson('/api/v1/discovery-sources', $this->source())->assertCreated();
        $this->postJson('/api/v1/discovery-sources', $this->source(text: '# Changed instructions'))
            ->assertOk()->assertJsonPath('data.status', 'changed');
        $this->assertDatabaseHas('skills', ['source_markdown' => "# Useful skill\nDo a task."]);
    }

    public function test_a_new_revision_of_an_existing_catalog_source_is_reported_without_duplication(): void
    {
        Skill::factory()->create([
            ...$this->source(), 'source_content_hash' => null,
        ]);
        $changed = $this->source(text: '# New revision');
        $changed['source_commit'] = str_repeat('b', 40);
        $changed['source_url'] = str_replace(str_repeat('a', 40), str_repeat('b', 40), $changed['source_url']);
        $this->withToken($this->token)->postJson('/api/v1/discovery-sources', $changed)
            ->assertOk()->assertJsonPath('data.status', 'changed');
        $this->assertDatabaseCount('skills', 1);
    }

    public function test_controller_can_claim_and_complete_a_gitlab_or_bitbucket_draft_without_publishing(): void
    {
        foreach ([['gitlab.com', '-/blob'], ['bitbucket.org', 'src']] as [$host, $marker]) {
            $created = $this->withToken($this->token)->postJson('/api/v1/discovery-sources', $this->source($host, $marker, '# '.$host))->assertCreated();
            $claim = $this->withToken(str_repeat('q', 40))->postJson('/api/v1/skill-quality-reviews/claim', [])->assertOk();
            $claim->assertJsonPath('data.id', $created->json('data.skill_id'));
            $this->patchJson('/api/v1/skill-quality-reviews/'.$claim->json('data.id'), [
                'lease_token' => $claim->json('data.lease_token'), 'changes' => ['summary' => 'Reviewed description'],
            ])->assertOk()->assertJsonPath('data.is_published', false);
        }
    }
}
