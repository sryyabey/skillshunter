<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $notes = [
            'github-genspark-ai-genoffice-skills-recommended-slide-planner-17d9e4e4f442' => [
                'The skill emphasizes creating a narrative outline before building the presentation, which ensures clarity and impact. This approach is ideal for scenarios where the story is more important than the template.',
                'Have your audience, desired action, and time or slide budget ready. The skill first gives you one title and key message per slide to approve before it builds the PowerPoint deck.',
                'Wait for the user to confirm or edit.',
            ],
            'github-elayadesign-ai-design-skills-skills-landing-page-design-68d96e09eb85' => [
                'This skill ensures landing pages are optimized for conversion by focusing on one offer, one audience, and one primary action. It includes rules for layout, copywriting, and accessibility, and is applicable to any landing page, marketing site, or web UI component.',
                'Bring one offer, one target audience, and one primary action. Before writing code, the skill drafts the page outline, hero copy, calls to action, and FAQ, then recommends a layout.',
                'before writing code',
            ],
        ];

        foreach ($notes as $slug => [$before, $after, $evidence]) {
            DB::table('skills')->where('slug', $slug)->where('editor_note', $before)
                ->where('source_markdown', 'like', '%'.$evidence.'%')
                ->update(['editor_note' => $after, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Retain source-backed edits.
    }
};
