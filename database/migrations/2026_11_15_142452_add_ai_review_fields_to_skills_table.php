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
            $table->timestamp('ai_reviewed_at')->nullable();
            $table->char('ai_review_lease_hash', 64)->nullable();
            $table->timestamp('ai_review_lease_until')->nullable();
            $table->index(['ai_reviewed_at', 'created_at', 'id'], 'skills_ai_review_order_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropIndex('skills_ai_review_order_index');
            $table->dropColumn(['ai_reviewed_at', 'ai_review_lease_hash', 'ai_review_lease_until']);
        });
    }
};
