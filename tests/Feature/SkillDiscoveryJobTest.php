<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SkillDiscoveryJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillDiscoveryJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_claims_renews_and_completes_a_job(): void
    {
        $token = str_repeat('a', 64);
        config(['skill-integration.token' => $token]);
        $category = Category::factory()->create();
        $job = SkillDiscoveryJob::create(['topic' => 'PowerPoint', 'category_id' => $category->id]);

        $this->postJson('/api/v1/discovery-jobs/claim')->assertUnauthorized();
        $claim = $this->withToken($token)->postJson('/api/v1/discovery-jobs/claim')->assertOk()
            ->assertJsonPath('data.id', $job->id)->assertJsonPath('data.category_slug', $category->slug);
        $this->postJson('/api/v1/discovery-jobs/claim')->assertOk()->assertJsonPath('data', null);

        $url = '/api/v1/discovery-jobs/'.$job->id;
        $lease = $claim->json('data.lease_token');
        $this->patchJson($url, ['lease_token' => str_repeat('b', 64), 'status' => 'completed', 'results' => []])
            ->assertConflict();
        $this->patchJson($url, ['lease_token' => $lease, 'status' => 'running', 'results' => []])->assertOk();
        $this->patchJson($url, ['lease_token' => $lease, 'status' => 'completed', 'results' => []])
            ->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertDatabaseHas('skill_discovery_jobs', ['id' => $job->id, 'status' => 'completed', 'attempts' => 1]);
    }

    public function test_expired_lease_is_reclaimed_and_previous_worker_is_rejected(): void
    {
        $token = str_repeat('a', 64);
        config(['skill-integration.token' => $token]);
        $category = Category::factory()->create();
        $job = SkillDiscoveryJob::create(['topic' => 'PDF', 'category_id' => $category->id]);

        $first = $this->withToken($token)->postJson('/api/v1/discovery-jobs/claim')->json('data.lease_token');
        $this->travel(41)->minutes();
        $second = $this->postJson('/api/v1/discovery-jobs/claim')->assertOk()->json('data.lease_token');
        $this->assertNotSame($first, $second);

        $this->patchJson('/api/v1/discovery-jobs/'.$job->id, [
            'lease_token' => $first, 'status' => 'completed', 'results' => [],
        ])->assertConflict();

        $this->travel(41)->minutes();
        $this->postJson('/api/v1/discovery-jobs/claim')->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseHas('skill_discovery_jobs', ['id' => $job->id, 'status' => 'failed', 'attempts' => 2]);
    }

    public function test_retryable_failure_stops_after_the_second_attempt(): void
    {
        $token = str_repeat('a', 64);
        config(['skill-integration.token' => $token]);
        $category = Category::factory()->create();
        $job = SkillDiscoveryJob::create(['topic' => 'React', 'category_id' => $category->id]);
        $claimUrl = '/api/v1/discovery-jobs/claim';
        $jobUrl = '/api/v1/discovery-jobs/'.$job->id;
        $failure = ['status' => 'failed', 'results' => [], 'error' => 'Temporary error.', 'retryable' => true];

        $first = $this->withToken($token)->postJson($claimUrl)->assertOk()->json('data.lease_token');
        $this->patchJson($jobUrl, $failure + ['lease_token' => $first])
            ->assertOk()->assertJsonPath('data.status', 'queued');
        $this->postJson($claimUrl)->assertOk()->assertJsonPath('data', null);

        $this->travel(11)->minutes();
        $second = $this->postJson($claimUrl)->assertOk()->json('data.lease_token');
        $this->patchJson($jobUrl, $failure + ['lease_token' => $second])
            ->assertOk()->assertJsonPath('data.status', 'failed');
        $this->postJson($claimUrl)->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseHas('skill_discovery_jobs', ['id' => $job->id, 'status' => 'failed', 'attempts' => 2]);

        $job->forceFill(['status' => 'queued', 'available_at' => now()->addMinutes(10)])->save();
        $this->postJson($claimUrl)->assertOk()->assertJsonPath('data', null);
        $this->assertDatabaseHas('skill_discovery_jobs', [
            'id' => $job->id, 'status' => 'failed', 'attempts' => 2, 'available_at' => null,
        ]);
    }
}
