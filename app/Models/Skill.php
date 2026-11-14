<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'title', 'slug', 'summary', 'editor_note', 'instructions', 'license',
        'version', 'verified_at', 'is_published', 'use_cases', 'limitations', 'compatibility',
        'setup_tip', 'sample_input', 'sample_output', 'source_url', 'source_markdown', 'source_commit',
        'seo_title', 'seo_description', 'seo_keywords',
    ];

    protected static function booted(): void
    {
        static::saving(function (Skill $skill): void {
            if (! $skill->source_commit && ! $skill->getOriginal('source_commit')) {
                return;
            }

            $sourceChanged = $skill->exists && $skill->isDirty(['source_url', 'source_commit', 'source_markdown']);
            if ($sourceChanged) {
                $skill->verified_at = null;
                $skill->approved_by = null;
                $skill->is_published = false;
            } elseif ($skill->isDirty('verified_at')) {
                $skill->approved_by = $skill->verified_at ? Auth::id() : null;
                if (! $skill->verified_at) {
                    $skill->is_published = false;
                }
            }

            if ($skill->source_commit && (! preg_match('/\A[0-9a-f]{40}\z/', $skill->source_commit)
                || ! preg_match('~\Ahttps://github\.com/[\w.-]+/[\w.-]+/blob/'.preg_quote($skill->source_commit, '~').'/(?:[^?#]+/)?SKILL\.md\z~i', $skill->source_url ?? ''))) {
                throw ValidationException::withMessages([
                    'source_url' => 'Link to SKILL.md at the recorded GitHub commit.',
                ]);
            }

            if ($skill->is_published && (! $skill->verified_at || ! $skill->approved_by
                || ! $skill->source_commit || blank($skill->source_markdown) || blank($skill->source_url))) {
                throw ValidationException::withMessages([
                    'is_published' => 'Approve the pinned SKILL.md as an editor before publishing.',
                ]);
            }
        });
    }

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

    public function publicInstructions(): ?string
    {
        return filled($this->source_url) ? $this->source_markdown : $this->instructions;
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
