<?php

namespace Tests\Feature;

use App\Jobs\SubmitIndexNowUrls;
use App\Models\Skill;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        config(['indexnow.enabled' => true, 'indexnow.connection' => 'database']);
        Queue::fake();
        Http::preventStrayRequests();
    }

    public function test_only_public_changes_are_queued_including_old_slug_and_removal(): void
    {
        $skill = Skill::factory()->create();
        $skill->update(['title' => 'Draft update']);
        Queue::assertNothingPushed();
        $skill->update(['is_published' => true]);
        Queue::assertPushed(SubmitIndexNowUrls::class, 1);
        $old = $skill->slug;
        $skill->update(['slug' => 'new-public-slug']);
        Queue::assertPushed(SubmitIndexNowUrls::class, fn ($job) => $job->slugs === ['new-public-slug', $old]);
        $skill->forceFill(['ai_review_details' => ['internal' => true]])->save();
        Queue::assertPushed(SubmitIndexNowUrls::class, 2);
        $skill->update(['is_published' => false]);
        Queue::assertPushed(SubmitIndexNowUrls::class, 3);
        $skill->delete();
        Queue::assertPushed(SubmitIndexNowUrls::class, 3);
        $published = Skill::factory()->published()->create();
        $published->delete();
        Queue::assertPushed(SubmitIndexNowUrls::class, 5);
    }

    public function test_rollback_does_not_submit_and_commit_dispatches_afterwards(): void
    {
        DB::beginTransaction();
        Skill::factory()->published()->create();
        Queue::assertNothingPushed();
        DB::rollBack();
        Queue::assertNothingPushed();
        DB::transaction(function (): void {
            Skill::factory()->published()->create();
            Queue::assertNothingPushed();
        });
        Queue::assertPushed(SubmitIndexNowUrls::class, 1);
    }

    public function test_job_submits_canonical_urls_to_indexnow(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 202)]);
        (new SubmitIndexNowUrls(['example', 'example']))->handle();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request['host'] === 'skillshunter.net'
            && $request['keyLocation'] === 'https://skillshunter.net/'.config('indexnow.key').'.txt'
            && $request['urlList'] === ['https://skillshunter.net/skills/example']);
    }

    public function test_http_failures_are_retryable_and_disabled_integration_sends_nothing(): void
    {
        Http::fake(['api.indexnow.org/*' => Http::response('', 503)]);
        try {
            (new SubmitIndexNowUrls(['example']))->handle();
            $this->fail('Submission failure must trigger queue retry.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('503', $exception->getMessage());
        }
        config(['indexnow.enabled' => false]);
        Skill::factory()->published()->create();
        Queue::assertNothingPushed();
        (new SubmitIndexNowUrls(['example']))->handle();
        Http::assertSentCount(1);
    }
}
