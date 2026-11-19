<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SubmitIndexNowUrls implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    /** @param list<string> $slugs */
    public function __construct(public array $slugs) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 1800, 3600];
    }

    public function handle(): void
    {
        if (! config('indexnow.enabled')) {
            return;
        }
        $key = config('indexnow.key');
        if (! is_string($key) || ! preg_match('/\A[a-zA-Z0-9-]{8,128}\z/', $key)) {
            throw new RuntimeException('IndexNow key is missing or invalid.');
        }
        $urls = array_values(array_unique(array_map(
            fn (string $slug): string => 'https://skillshunter.net'.route('detail', ['slug' => $slug], false),
            $this->slugs,
        )));
        if ($urls === []) {
            return;
        }
        $response = Http::acceptJson()->connectTimeout(5)->timeout(15)
            ->withOptions(['allow_redirects' => false])
            ->post('https://api.indexnow.org/indexnow', [
                'host' => 'skillshunter.net',
                'key' => $key,
                'keyLocation' => 'https://skillshunter.net/'.$key.'.txt',
                'urlList' => $urls,
            ]);
        if ($response->status() === 429) {
            $this->release(max(60, min(86400, (int) $response->header('Retry-After') ?: 300)));

            return;
        }
        if (! in_array($response->status(), [200, 202], true)) {
            throw new RuntimeException('IndexNow submission returned HTTP '.$response->status());
        }
        Log::info('IndexNow URLs submitted.', ['urls' => $urls, 'status' => $response->status()]);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('IndexNow submission failed after retries.', ['slugs' => $this->slugs, 'error' => $exception?->getMessage()]);
    }
}
