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
        Schema::table('skills', function (Blueprint $table) {
            $table->timestamp('ai_review_rejected_at')->nullable()->index();
            $table->string('ai_review_rejection_reason', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropIndex(['ai_review_rejected_at']);
            $table->dropColumn(['ai_review_rejected_at', 'ai_review_rejection_reason']);
        });
    }
};
