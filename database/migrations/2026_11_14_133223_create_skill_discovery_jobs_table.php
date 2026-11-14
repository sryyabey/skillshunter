<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_discovery_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('topic', 160);
            $table->foreignId('category_id')->constrained();
            $table->unsignedTinyInteger('result_limit')->default(3);
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('lease_token', 64)->nullable();
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->json('results')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
            $table->index(['status', 'lease_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_discovery_jobs');
    }
};
