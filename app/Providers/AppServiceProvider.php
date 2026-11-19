<?php

namespace App\Providers;

use App\Jobs\SubmitIndexNowUrls;
use App\Models\Skill;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Skill::saved(function (Skill $skill): void {
            if (! $skill->is_published && ! $skill->getOriginal('is_published')) {
                return;
            }
            if ($skill->getRawOriginal('id') !== null && ! $skill->wasChanged([
                'is_published', 'slug', 'category_id', 'title', 'summary', 'editor_note',
                'instructions', 'source_url', 'source_markdown', 'source_commit', 'license',
                'version', 'verified_at', 'use_cases', 'limitations', 'compatibility',
                'setup_tip', 'sample_input', 'sample_output', 'seo_title', 'seo_description', 'seo_keywords',
            ])) {
                return;
            }
            $slugs = [$skill->slug];
            if ($skill->wasChanged('slug') && $skill->getOriginal('is_published')) {
                $slugs[] = $skill->getOriginal('slug');
            }
            $this->submitIndexNowAfterCommit($slugs);
        });
        Skill::deleted(function (Skill $skill): void {
            if ($skill->is_published) {
                $this->submitIndexNowAfterCommit([$skill->slug]);
            }
        });
    }

    /** @param list<string> $slugs */
    private function submitIndexNowAfterCommit(array $slugs): void
    {
        if (! config('indexnow.enabled')) {
            return;
        }
        DB::afterCommit(function () use ($slugs): void {
            try {
                $connection = config('indexnow.connection');
                if (! in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis', 'sqs', 'beanstalkd'], true)) {
                    throw new \RuntimeException('IndexNow requires a persistent asynchronous queue connection.');
                }
                SubmitIndexNowUrls::dispatch(array_values(array_unique($slugs)))->onConnection($connection);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
