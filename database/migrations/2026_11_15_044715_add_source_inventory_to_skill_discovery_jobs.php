<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('skill_discovery_jobs', function (Blueprint $table) {
            $table->foreignId('source_file_id')->nullable()->unique()->constrained('skill_source_files')->restrictOnDelete();
            $table->char('source_blob_sha', 40)->nullable()->unique();
            $table->json('source_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skill_discovery_jobs', function (Blueprint $table) {
            $table->dropForeign(['source_file_id']);
            $table->dropUnique(['source_file_id']);
            $table->dropUnique(['source_blob_sha']);
            $table->dropColumn(['source_file_id', 'source_blob_sha', 'source_snapshot']);
        });
    }
};
