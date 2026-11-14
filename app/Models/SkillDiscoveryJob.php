<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillDiscoveryJob extends Model
{
    protected $fillable = ['topic', 'category_id', 'result_limit'];

    protected $hidden = ['lease_token'];

    protected function casts(): array
    {
        return ['lease_until' => 'datetime', 'available_at' => 'datetime', 'results' => 'array'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
