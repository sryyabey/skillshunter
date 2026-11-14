<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'title', 'slug', 'summary', 'editor_note', 'instructions', 'license',
        'version', 'verified_at', 'is_published', 'use_cases', 'limitations', 'compatibility',
        'setup_tip', 'sample_input', 'sample_output', 'source_url',
        'seo_title', 'seo_description', 'seo_keywords',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'date',
            'is_published' => 'boolean',
            'use_cases' => 'array',
            'limitations' => 'array',
            'compatibility' => 'array',
            'seo_keywords' => 'array',
        ];
    }

    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('is_published', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function approvedReviews(): HasMany
    {
        return $this->reviews()->approved();
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(Review::class)->ofMany(['created_at' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->approved()
        );
    }

    public function votes(): HasMany
    {
        return $this->hasMany(CatalogVote::class, 'skill_id');
    }
}
