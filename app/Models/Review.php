<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    use HasFactory;

    public const VERDICTS = ['Exceeded Expectations', 'Helpful', 'Needs Fine-Tuning'];

    protected $fillable = ['skill_id', 'author', 'model', 'verdict', 'body', 'is_approved'];

    protected function casts(): array
    {
        return ['is_approved' => 'boolean'];
    }

    #[Scope]
    protected function approved(Builder $query): void
    {
        $query->where('is_approved', true);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewVote::class);
    }
}
