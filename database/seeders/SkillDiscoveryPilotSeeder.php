<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\SkillDiscoveryJob;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SkillDiscoveryPilotSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            ['Scanned PDF OCR', 'content-editorial'],
            ['Excel formula debugging', 'data-research'],
            ['SQL query generation', 'data-research'],
            ['PowerPoint slide layout design', 'product-design'],
            ['Claude Code project instructions', 'software-development'],
            ['Keyword intent clustering', 'content-editorial'],
            ['Google Sheets formulas', 'data-research'],
            ['PDF table extraction', 'content-editorial'],
            ['Playwright end-to-end tests', 'software-development'],
            ['Meeting minutes summarization', 'content-editorial'],
            ['Docker Compose development', 'software-development'],
            ['Figma design system documentation', 'product-design'],
            ['Ollama structured output', 'software-development'],
            ['API documentation writing', 'content-editorial'],
            ['Python file automation', 'software-development'],
            ['Excel pivot table reporting', 'data-research'],
            ['Next.js route architecture', 'software-development'],
            ['React component refactoring', 'software-development'],
            ['Data warehouse star schema', 'data-research'],
            ['Product requirements document', 'product-design'],
        ];

        DB::transaction(function () use ($topics): void {
            $slugs = array_unique(array_column($topics, 1));
            $categories = Category::whereIn('slug', $slugs)->pluck('id', 'slug');
            $missing = array_diff($slugs, $categories->keys()->all());

            if ($missing !== []) {
                throw new RuntimeException('Missing discovery categories: '.implode(', ', $missing));
            }

            foreach ($topics as [$topic, $categorySlug]) {
                SkillDiscoveryJob::firstOrCreate(
                    ['topic' => $topic, 'category_id' => $categories[$categorySlug]],
                    ['result_limit' => 1],
                );
            }
        });
    }
}
