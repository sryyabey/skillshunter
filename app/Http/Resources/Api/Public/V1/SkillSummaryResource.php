<?php

namespace App\Http\Resources\Api\Public\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SkillSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        abort_unless($this->is_published, 404);

        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            'category' => $this->category ? ['slug' => $this->category->slug, 'name' => $this->category->name] : null,
            'url' => route('detail', $this->slug),
            'json_url' => route('api.public.v1.skills.show', $this->slug),
            'markdown_url' => route('skills.markdown', $this->slug),
            'download_url' => route('skills.download', $this->slug),
            'source_url' => $this->source_url,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
