<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $query = DB::table('skills')->where('slug', 'github-genspark-ai-genoffice-skills-recommended-slide-planner-17d9e4e4f442')
                ->whereNotNull('ai_reviewed_at')->whereNull('sample_input')->whereNull('sample_output');
            $skill = (clone $query)->lockForUpdate()->first();
            $input = 'Turn this material into a 10-slide report deck';

            if (! $skill || ! preg_match('/^quickPrompts:[ \t]+'.preg_quote($input, '/').'[ \t]+\|/m', $skill->source_markdown ?? '')) {
                return;
            }

            $details = $skill->ai_review_details ? json_decode($skill->ai_review_details, true, 512, JSON_THROW_ON_ERROR) : [
                'source_url' => $skill->source_url,
                'source_commit' => $skill->source_commit,
                'changes' => [],
            ];
            $details['changes'][] = ['field' => 'sample_input', 'before' => null, 'after' => $input];
            $updates = ['sample_input' => $input, 'updated_at' => now()];

            if (blank($skill->editor_note) && str_contains($skill->source_markdown, 'slide-by-slide outline')
                && str_contains($skill->source_markdown, 'Wait for the user to confirm or edit.')) {
                $note = 'The first response is a slide-by-slide outline with a title and one key message per slide. Confirm the outline before the PowerPoint is built.';
                $updates['editor_note'] = $note;
                $details['changes'][] = ['field' => 'editor_note', 'before' => $skill->editor_note, 'after' => $note];
            }

            $query->update($updates + ['ai_review_details' => json_encode($details, JSON_THROW_ON_ERROR)]);
        });
    }

    public function down(): void
    {
        // Keep source-backed editorial changes and any subsequent editor work.
    }
};
