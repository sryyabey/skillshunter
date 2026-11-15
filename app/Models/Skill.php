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

    protected $hidden = ['ai_review_lease_hash'];

    protected static function booted(): void
    {
        static::saving(function (Skill $skill): void {
            if ($skill->is_published && preg_match('/^ {0,3}#{1,6} Insert instructions below[.!]?[ \t]*$/mi', $skill->source_markdown ?? '')) {
                throw ValidationException::withMessages([
                    'is_published' => 'Replace the placeholder instructions before publishing.',
                ]);
            }

            $sourceChanged = $skill->exists && $skill->isDirty(['source_url', 'source_commit', 'source_markdown']);
            if ($sourceChanged) {
                $skill->ai_reviewed_at = null;
                $skill->ai_review_details = null;
                $skill->ai_review_lease_hash = null;
                $skill->ai_review_lease_until = null;
            }

            if (! $skill->source_commit && ! $skill->getOriginal('source_commit')) {
                return;
            }
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
            'ai_reviewed_at' => 'datetime',
            'ai_review_details' => 'array',
            'ai_review_lease_until' => 'datetime',
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

    #[Scope]
    protected function catalogSearch(Builder $query, string $search): void
    {
        preg_match_all('/[\p{L}\p{N}]+(?:[+#]+)?/u', mb_strtolower($search), $matches);
        $tokens = array_unique($matches[0]);
        $meaningful = array_diff($tokens, ['a', 'an', 'and', 'for', 'of', 'the', 'to', 've', 'için', 'ile']);
        $terms = array_slice($meaningful ?: $tokens, 0, 10);
        if ($terms === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $searchable = ['title', 'seo_keywords', 'seo_title', 'slug', 'summary', 'editor_note', 'use_cases'];
        $rank = [];
        $bindings = [];
        $phrase = '%'.implode('%', array_map(self::escapeSearchTerm(...), $terms)).'%';
        $rank[] = "CASE WHEN LOWER(title) LIKE ? ESCAPE '!' THEN 30 ELSE 0 END";
        $bindings[] = $phrase;

        foreach ($terms as $term) {
            $pattern = '%'.self::escapeSearchTerm($term).'%';
            $variants = array_map(fn (string $variant): string => '%'.self::escapeSearchTerm($variant).'%', self::searchVariants($term));

            $query->where(function (Builder $match) use ($searchable, $variants): void {
                foreach ($variants as $variant) {
                    foreach ($searchable as $field) {
                        $match->orWhereRaw("LOWER({$field}) LIKE ? ESCAPE '!'", [$variant]);
                    }
                    $match->orWhereHas('category', fn (Builder $category) => $category->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$variant]));
                }
            });

            foreach (['title' => 18, 'seo_keywords' => 12, 'seo_title' => 8, 'slug' => 5, 'summary' => 3, 'use_cases' => 2] as $field => $weight) {
                $rank[] = "CASE WHEN LOWER({$field}) LIKE ? ESCAPE '!' THEN {$weight} ELSE 0 END";
                $bindings[] = $pattern;
            }
        }

        $query->orderByRaw('('.implode(' + ', $rank).') DESC', $bindings);
    }

    private static function escapeSearchTerm(string $term): string
    {
        return strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']);
    }

    private static function searchVariants(string $term): array
    {
        foreach ([
            ['excel', 'xlsx', 'spreadsheet', 'spreadsheets'],
            ['word', 'docx'],
            ['powerpoint', 'pptx', 'presentation', 'presentations', 'slides', 'sunum', 'slayt'],
            ['automation', 'otomasyon'],
            ['report', 'rapor'],
        ] as $group) {
            if (in_array($term, $group, true)) {
                return $group;
            }
        }

        return [$term];
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
