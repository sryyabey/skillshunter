<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_source_repositories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('github_id')->unique();
            $table->string('full_name');
            $table->string('default_branch');
            $table->char('last_scanned_commit', 40);
            $table->timestamps();
        });

        Schema::create('skill_source_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_source_repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->text('path');
            $table->char('path_hash', 64);
            $table->char('commit_sha', 40);
            $table->char('blob_sha', 40)->index();
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->unique(['skill_source_repository_id', 'path_hash'], 'skill_source_repo_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_source_files');
        Schema::dropIfExists('skill_source_repositories');
    }
};
