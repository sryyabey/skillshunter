<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skills', function (Blueprint $table): void {
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 170)->nullable();
            $table->json('seo_keywords')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'seo_description', 'seo_keywords']);
        });
    }
};
