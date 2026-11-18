<?php

namespace App\Http\Resources\Api\Public\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        abort_unless($this->is_published, 404);

        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'url' => route('detail', $this->slug),
            'json_url' => route('api.public.v1.skills.show', $this->slug),
            'markdown_url' => route('skills.markdown', $this->slug),
            'download_url' => route('skills.download', $this->slug),
            'category' => $this->category ? [
                'slug' => $this->category->slug,
                'name' => $this->category->name,
            ] : null,
            'editor_note' => $this->editor_note,
            'instructions' => $this->resource->publicInstructions(),
            'use_cases' => $this->use_cases ?? [],
            'limitations' => $this->limitations ?? [],
            'compatibility' => $this->compatibility ?? [],
            'setup_tip' => $this->setup_tip,
            'sample_input' => $this->sample_input,
            'sample_output' => $this->sample_output,
            'source_url' => $this->source_url,
            'source_commit' => $this->source_commit,
            'license' => $this->license,
            'version' => $this->version,
            'listing_approved_at' => $this->verified_at?->toDateString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'seo_title' => $this->seo_title,
            'seo_description' => $this->seo_description,
            'seo_keywords' => $this->seo_keywords ?? [],
        ];
    }
}
