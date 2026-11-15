<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

#[Signature('skills:scan-sources
    {repositories?* : Additional GitHub owner/repo entries}
    {--curated : Include skills.sh curated sources}
    {--pages=0 : Number of skills.sh leaderboard pages to include}
    {--limit=30 : Maximum repositories to scan}
    {--apply : Save the inventory to the database}')]
#[Description('Preview or save a topic-free inventory of GitHub SKILL.md files')]
class ScanSkillSources extends Command
{
    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 250]]);
        $pages = filter_var($this->option('pages'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 20]]);
        if ($limit === false || $pages === false) {
            $this->error('Use --limit=1..250 and --pages=0..20.');

            return self::INVALID;
        }

        $catalog = [];
        foreach (DB::table('skills')->whereNotNull('source_url')->select('id', 'source_url')->lazyById() as $skill) {
            if (preg_match('~\Ahttps://github\.com/([\w.-]+/[\w.-]+)(?:/blob/[^/]+/(.+))?/?\z~i', $skill->source_url, $url)) {
                $name = strtolower($url[1]);
                $catalog[$name] ??= [];
                if (isset($url[2])) {
                    $catalog[$name][rawurldecode($url[2])] ??= $skill->id;
                }
            }
        }

        try {
            $repositories = array_values(array_unique(array_map('strtolower', [
                ...$this->argument('repositories'),
                ...array_keys($catalog),
                ...$this->directorySources($pages),
            ])));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $failed = false;
        $seenRepositories = [];
        foreach (array_slice($repositories, 0, $limit) as $name) {
            try {
                if (! preg_match('~\A[A-Za-z0-9][A-Za-z0-9-]{0,38}/[A-Za-z0-9_.-]{1,100}\z~', $name)
                    || in_array(explode('/', $name)[1], ['.', '..'], true)) {
                    throw new RuntimeException('Expected a GitHub owner/repo name.');
                }
                $repository = $this->getJson('https://api.github.com/repos/'.$name, 'SKILLSHUNTER_GITHUB_TOKEN');
                if (($repository['private'] ?? true) || ($repository['archived'] ?? true)) {
                    $this->warn("Skipped {$name} (private or archived).");

                    continue;
                }
                if (! is_int($repository['id'] ?? null) || ! is_string($repository['default_branch'] ?? null)
                    || ! is_string($repository['full_name'] ?? null)) {
                    throw new RuntimeException('Incomplete GitHub repository response.');
                }
                if (isset($seenRepositories[$repository['id']])) {
                    continue;
                }
                $branch = rawurlencode($repository['default_branch']);
                $commit = $this->getJson("https://api.github.com/repos/{$name}/commits/{$branch}", 'SKILLSHUNTER_GITHUB_TOKEN')['sha'] ?? '';
                if (! preg_match('/\A[0-9a-f]{40}\z/', $commit)) {
                    throw new RuntimeException('Invalid GitHub commit SHA.');
                }
                $tree = $this->getJson("https://api.github.com/repos/{$name}/git/trees/{$commit}", 'SKILLSHUNTER_GITHUB_TOKEN', ['recursive' => 1]);
                if (($tree['truncated'] ?? true) || ! is_array($tree['tree'] ?? null)) {
                    throw new RuntimeException('GitHub returned an incomplete file tree.');
                }
                $files = $this->sourceFiles($tree['tree'], $catalog[$name] ?? []);
                $seenRepositories[$repository['id']] = true;
                $linked = count(array_filter(array_column($files, 'skill_id')));
                $this->line("{$repository['full_name']} | ".count($files)." files | {$linked} linked to catalog");
                if ($this->option('apply')) {
                    $this->saveInventory($repository, $commit, $files);
                }
            } catch (Throwable $exception) {
                $failed = true;
                $status = $exception instanceof RequestException ? 'HTTP '.$exception->response->status() : $exception->getMessage();
                $this->warn("{$name}: {$status}");
                if ($exception instanceof RequestException && in_array($exception->response->status(), [403, 429], true)) {
                    break;
                }
            }
        }

        $this->info($this->option('apply') ? 'Inventory saved for successful scans.' : 'Preview only. No inventory saved.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function directorySources(int $pages): array
    {
        $rows = [];
        if ($this->option('curated')) {
            $curated = $this->getJson('https://skills.sh/api/v1/skills/curated', 'VERCEL_OIDC_TOKEN');
            foreach ($curated['data'] as $owner) {
                array_push($rows, ...$owner['skills']);
            }
        }
        for ($page = 0; $page < $pages; $page++) {
            $list = $this->getJson('https://skills.sh/api/v1/skills', 'VERCEL_OIDC_TOKEN', ['page' => $page, 'per_page' => 100]);
            array_push($rows, ...$list['data']);
            if (! ($list['pagination']['hasMore'] ?? false)) {
                break;
            }
        }

        return array_values(array_filter(array_map(
            fn (array $row): ?string => ($row['sourceType'] ?? null) === 'github' && empty($row['isDuplicate']) && is_string($row['source'] ?? null)
                ? $row['source'] : null,
            $rows,
        )));
    }

    private function getJson(string $url, string $tokenName, array $query = []): array
    {
        $request = Http::acceptJson()->withUserAgent('SkillsHunter-SourceInventory')->timeout(20)->withoutRedirecting();
        $token = getenv($tokenName);
        if ($token !== false && $token !== '') {
            $request->withToken($token);
        }

        $response = $request->get($url, $query)->throw();
        $data = $response->json();
        if (! $response->ok() || ! is_array($data)) {
            throw new RuntimeException('Unexpected response from source directory.');
        }

        return $data;
    }

    private function sourceFiles(array $tree, array $catalog): array
    {
        $files = [];
        foreach ($tree as $entry) {
            if ($entry['type'] !== 'blob' || ($entry['mode'] ?? null) === '120000'
                || ! preg_match('~(?:^|/)SKILL\.md$~iD', $entry['path'])
                || ! preg_match('/\A[0-9a-f]{40}\z/', $entry['sha'])) {
                continue;
            }
            $path = $entry['path'];
            $files[] = [
                'path' => $path,
                'path_hash' => hash('sha256', $path),
                'blob_sha' => $entry['sha'],
                'skill_id' => $catalog[$path] ?? null,
            ];
        }

        return $files;
    }

    private function saveInventory(array $repository, string $commit, array $files): void
    {
        DB::transaction(function () use ($repository, $commit, $files): void {
            $now = now();
            DB::table('skill_source_repositories')->upsert([[
                'github_id' => $repository['id'],
                'full_name' => $repository['full_name'],
                'default_branch' => $repository['default_branch'],
                'last_scanned_commit' => $commit,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['github_id'], ['full_name', 'default_branch', 'last_scanned_commit', 'updated_at']);
            $repositoryId = DB::table('skill_source_repositories')->where('github_id', $repository['id'])->value('id');
            $inventory = DB::table('skill_source_files')->where('skill_source_repository_id', $repositoryId);
            (clone $inventory)->whereNull('removed_at')->update(['removed_at' => $now]);
            $rows = array_map(fn (array $file): array => $file + [
                'skill_source_repository_id' => $repositoryId,
                'commit_sha' => $commit,
                'removed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ], $files);
            foreach (array_chunk($rows, 100) as $batch) {
                DB::table('skill_source_files')->upsert($batch, ['skill_source_repository_id', 'path_hash'], [
                    'blob_sha', 'commit_sha', 'removed_at', 'updated_at',
                ]);
                foreach (array_filter($batch, fn (array $row): bool => $row['skill_id'] !== null) as $row) {
                    (clone $inventory)->where('path_hash', $row['path_hash'])->whereNull('skill_id')->update(['skill_id' => $row['skill_id']]);
                }
            }
        }, attempts: 3);
    }
}
