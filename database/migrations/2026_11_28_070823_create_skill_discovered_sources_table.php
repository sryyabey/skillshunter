<?php

use App\Support\SkillSourceReference;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table): void {
            $table->char('source_content_hash', 64)->nullable()->index();
            $table->char('source_identity_hash', 64)->nullable()->index();
        });
        foreach (DB::table('skills')->select('id', 'source_markdown', 'source_url', 'source_commit')->lazyById() as $skill) {
            $reference = $skill->source_commit ? SkillSourceReference::parse($skill->source_url ?? '', $skill->source_commit) : null;
            DB::table('skills')->where('id', $skill->id)->update([
                'source_content_hash' => filled($skill->source_markdown) ? SkillSourceReference::fingerprint($skill->source_markdown) : null,
                'source_identity_hash' => $reference ? SkillSourceReference::identity($reference) : null,
            ]);
        }
        Schema::create('skill_discovered_sources', function (Blueprint $table): void {
            $table->id();
            $table->char('identity_hash', 64)->unique();
            $table->char('content_hash', 64)->unique();
            $table->string('platform', 20);
            $table->string('repository', 255);
            $table->text('path');
            $table->text('source_url');
            $table->char('source_commit', 40);
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_discovered_sources');
        Schema::table('skills', function (Blueprint $table): void {
            $table->dropIndex(['source_content_hash']);
            $table->dropIndex(['source_identity_hash']);
            $table->dropColumn(['source_content_hash', 'source_identity_hash']);
        });
    }
};
