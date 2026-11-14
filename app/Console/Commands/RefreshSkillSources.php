<?php

namespace App\Console\Commands;

use App\Models\Skill;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

#[Signature('skills:refresh-sources {slug? : Refresh a single skill}')]
#[Description('Import original SKILL.md text for existing GitHub listings')]
class RefreshSkillSources extends Command
{
    public function handle(): int
    {
        $skills = Skill::query()->whereNotNull('source_url')->whereNull('source_commit')
            ->when($this->argument('slug'), fn ($query, $slug) => $query->where('slug', $slug))
            ->lazyById();
        $failed = false;

        foreach ($skills as $skill) {
            if (! preg_match('~\Ahttps://github\.com/([\w.-]+/[\w.-]+)/blob/((?:[\w.%+-]+/)+SKILL\.md)\z~i', $skill->source_url, $source)) {
                $this->line("Skipped {$skill->slug}: no GitHub SKILL.md file URL.");

                continue;
            }

            $path = rawurldecode($source[2]);
            if (preg_match('~(?:^|/)\.{1,2}/|[^\w./ +-]~', $path)) {
                $this->warn("Skipped {$skill->slug}: unsupported source path.");

                continue;
            }

            try {
                $response = Http::timeout(15)->withoutRedirecting()
                    ->withOptions(['stream' => true])
                    ->get('https://raw.githubusercontent.com/'.$source[1].'/'.implode('/', array_map('rawurlencode', explode('/', $path))))
                    ->throw();

                if (! $response->ok()) {
                    throw new RuntimeException('Unexpected GitHub response status.');
                }

                $stream = $response->toPsrResponse()->getBody();
                $markdown = '';
                try {
                    while (! $stream->eof() && strlen($markdown) <= 400_000) {
                        $markdown .= $stream->read(8192);
                    }
                } finally {
                    $stream->close();
                }

                if (blank($markdown) || ! mb_check_encoding($markdown, 'UTF-8') || mb_strlen($markdown) > 100_000
                    || str_contains($markdown, "\0")) {
                    throw new RuntimeException('Invalid SKILL.md content.');
                }

                if ($skill->source_markdown !== $markdown) {
                    Skill::query()->whereKey($skill->id)->where('source_url', $skill->source_url)->whereNull('source_commit')
                        ->update(['source_markdown' => $markdown]);
                }
                $this->info("Imported {$skill->slug}.");
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("Could not import {$skill->slug}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
