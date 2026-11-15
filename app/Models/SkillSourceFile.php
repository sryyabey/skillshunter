<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SkillSourceFile extends Model
{
    public function repository(): BelongsTo
    {
        return $this->belongsTo(SkillSourceRepository::class, 'skill_source_repository_id');
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }

    public function discoveryJob(): HasOne
    {
        return $this->hasOne(SkillDiscoveryJob::class, 'source_file_id');
    }
}
