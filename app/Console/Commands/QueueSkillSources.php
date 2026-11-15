<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('skills:queue-sources
    {--repository= : Restrict to a GitHub owner/repo}
    {--category=software-development : Fallback category for editorial review}
    {--limit=5 : Maximum number of new drafts to queue}
    {--apply : Add the selected sources to the editorial queue}')]
#[Description('Preview or queue unique SKILL.md sources for editorial drafting')]
class QueueSkillSources extends Command
{
    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        $category = Category::where('slug', $this->option('category'))->first();
        if ($limit === false || ! $category) {
            $this->error('Use --limit=1..50 and an existing fallback category.');

            return self::INVALID;
        }

        $knownBlobs = [];
        foreach (Skill::query()->whereNotNull('source_markdown')->select('id', 'source_markdown')->lazyById() as $skill) {
            $markdown = $skill->source_markdown;
            $knownBlobs[sha1('blob '.strlen($markdown)."\0".$markdown)] = true;
        }

        $files = DB::table('skill_source_files as files')
            ->join('skill_source_repositories as repositories', 'repositories.id', '=', 'files.skill_source_repository_id')
            ->whereNull('files.removed_at')->whereNull('files.skill_id')
            ->when($this->option('repository'), fn ($query, $repo) => $query->whereRaw('LOWER(repositories.full_name) = ?', [strtolower($repo)]))
            ->whereNotExists(fn ($query) => $query->from('skill_source_files as linked')
                ->whereColumn('linked.blob_sha', 'files.blob_sha')->whereNotNull('linked.skill_id'))
            ->whereNotExists(fn ($query) => $query->from('skill_discovery_jobs as jobs')
                ->where(fn ($query) => $query->whereColumn('jobs.source_file_id', 'files.id')
                    ->orWhereColumn('jobs.source_blob_sha', 'files.blob_sha')))
            ->select('files.id', 'files.path', 'files.commit_sha', 'files.blob_sha', 'repositories.full_name')
            ->lazyById(100, 'files.id', 'id');

        $selected = 0;
        foreach ($files as $file) {
            if (isset($knownBlobs[$file->blob_sha])) {
                continue;
            }
            $knownBlobs[$file->blob_sha] = true;
            $snapshot = [
                'repository' => $file->full_name,
                'path' => $file->path,
                'commit' => $file->commit_sha,
                'blob' => $file->blob_sha,
            ];

            if ($this->option('apply')) {
                $inserted = DB::table('skill_discovery_jobs')->insertOrIgnore([
                    'topic' => Str::limit($file->full_name.'/'.$file->path, 160, ''),
                    'category_id' => $category->id,
                    'result_limit' => 1,
                    'source_file_id' => $file->id,
                    'source_blob_sha' => $file->blob_sha,
                    'source_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if (! $inserted) {
                    continue;
                }
            }

            $this->line($file->full_name.'/'.$file->path);
            if (++$selected === $limit) {
                break;
            }
        }

        $this->info($this->option('apply') ? "Queued {$selected} source(s)." : "Preview: {$selected} source(s).");

        return self::SUCCESS;
    }
}
