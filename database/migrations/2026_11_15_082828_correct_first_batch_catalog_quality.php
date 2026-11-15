<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('skills')->where('slug', 'github-anthropics-skills-template-6dc95cbf6d7a')
            ->where('source_markdown', 'like', '%# Insert instructions below%')
            ->update(['is_published' => false, 'verified_at' => null, 'approved_by' => null, 'updated_at' => now()]);

        foreach ([
            'anthropics-docx' => ['docx', 'Create and Edit Word Documents'],
            'anthropics-xlsx' => ['xlsx', 'Build and Recalculate Excel Spreadsheets'],
            'github-opensensenova-sensenova-skills-skills-sn-da-excel-workflow-capability-excel-data-cleaning-invalid-data-cleaning-0053a55d7dc1' => [
                'Invalid Data Cleaning', 'Clean Excel Text and Prepare Parquet Data',
            ],
        ] as $slug => [$original, $title]) {
            DB::table('skills')->where('slug', $slug)->where('title', $original)
                ->update(['title' => $title, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Editorial changes are retained so rollback cannot restore a known placeholder to the public catalog.
    }
};
