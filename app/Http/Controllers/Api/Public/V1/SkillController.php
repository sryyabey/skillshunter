<?php

namespace App\Http\Controllers\Api\Public\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Public\V1\SkillResource;
use App\Http\Resources\Api\Public\V1\SkillSummaryResource;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SkillController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['sometimes', 'required', 'string', 'max:100'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'page' => ['sometimes', 'required', 'integer', 'min:1', 'max:10000'],
            'per_page' => ['sometimes', 'required', 'integer', 'between:1,100'],
        ]);

        return SkillSummaryResource::collection(Skill::query()->published()->with('category')
            ->select(['id', 'category_id', 'is_published', 'slug', 'title', 'summary', 'source_url', 'updated_at'])
            ->when(isset($filters['q']), fn (Builder $query) => $query->catalogSearch($filters['q']))
            ->when(isset($filters['category']), fn (Builder $query) => $query->whereRelation('category', 'slug', $filters['category']))
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->appends($filters));
    }

    public function show(string $slug): SkillResource
    {
        return new SkillResource(Skill::query()->published()->with('category')->where('slug', $slug)->firstOrFail());
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::query()
            ->whereHas('skills', fn (Builder $query) => $query->published())
            ->orderBy('name')->get(['slug', 'name'])]);
    }
}
