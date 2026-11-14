<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ORIGINAL_CATEGORIES = [
        'github-claude-office-skills-skills-excel-automation-fc8b7f7d45ca' => 'data-research',
        'github-composio-community-awesome-codex-skills-composio-skills-ai-ml-api-automation-d1a655848208' => 'content-editorial',
    ];

    public function up(): void
    {
        foreach (self::ORIGINAL_CATEGORIES as $skill => $category) {
            $this->move($skill, $category, 'software-development');
        }
    }

    public function down(): void
    {
        foreach (self::ORIGINAL_CATEGORIES as $skill => $category) {
            $this->move($skill, 'software-development', $category);
        }
    }

    private function move(string $skill, string $from, string $to): void
    {
        $categoryIds = DB::table('categories')->whereIn('slug', [$from, $to])->pluck('id', 'slug');
        if ($categoryIds->count() === 2) {
            DB::table('skills')->where('slug', $skill)->where('category_id', $categoryIds[$from])
                ->update(['category_id' => $categoryIds[$to], 'updated_at' => now()]);
        }
    }
};
