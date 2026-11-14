<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->text('editor_note')->nullable();
            $table->longText('instructions');
            $table->string('license')->nullable();
            $table->string('version', 32)->nullable();
            $table->date('verified_at')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->json('use_cases')->nullable();
            $table->json('limitations')->nullable();
            $table->json('compatibility')->nullable();
            $table->text('setup_tip')->nullable();
            $table->longText('sample_input')->nullable();
            $table->longText('sample_output')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_id')->constrained()->cascadeOnDelete();
            $table->string('author', 100);
            $table->string('model', 100);
            $table->string('verdict', 32);
            $table->text('body');
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
            $table->index(['skill_id', 'is_approved']);
            $table->index(['is_approved', 'created_at']);
        });

        Schema::create('skill_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->string('author', 100);
            $table->string('title', 160);
            $table->text('body');
            $table->timestamps();
        });

        foreach (['skill', 'review'] as $subject) {
            Schema::create($subject.'_votes', function (Blueprint $table) use ($subject): void {
                $table->foreignId($subject.'_id')->constrained()->cascadeOnDelete();
                $table->char('voter', 64);
                $table->primary([$subject.'_id', 'voter']);
            });
        }
    }

    public function down(): void
    {
        foreach (['review_votes', 'skill_votes', 'skill_suggestions', 'reviews', 'skills', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
