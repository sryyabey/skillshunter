<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'category_slug' => $this->category->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'instructions' => $this->instructions,
            'source_url' => $this->source_url,
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords,
            'editor_note' => $this->editor_note,
            'license' => $this->license,
            'version' => $this->version,
            'use_cases' => $this->use_cases,
            'limitations' => $this->limitations,
            'compatibility' => $this->compatibility,
            'setup_tip' => $this->setup_tip,
            'sample_input' => $this->sample_input,
            'sample_output' => $this->sample_output,
            'is_published' => $this->is_published,
            'verified_at' => $this->verified_at?->toDateString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
