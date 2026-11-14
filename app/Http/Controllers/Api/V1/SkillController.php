<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertSkillRequest;
use App\Http\Resources\Api\V1\SkillResource;
use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SkillController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::orderBy('name')->get(['slug', 'name'])]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'required', 'in:draft,published'],
            'per_page' => ['sometimes', 'required', 'integer', 'between:1,100'],
        ]);

        return SkillResource::collection(Skill::query()->with('category')
            ->when(isset($filters['category']), fn (Builder $query) => $query->whereRelation('category', 'slug', $filters['category']))
            ->when(isset($filters['status']), fn (Builder $query) => $query->where('is_published', $filters['status'] === 'published'))
            ->orderBy('id')
            ->paginate((int) ($filters['per_page'] ?? 25))->withQueryString());
    }

    public function show(string $slug): SkillResource
    {
        return new SkillResource(Skill::with('category')->where('slug', $slug)->firstOrFail());
    }

    public function upsert(UpsertSkillRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = Category::where('slug', $data['category_slug'])->firstOrFail();
        $attributes = Arr::except($data, ['slug', 'category_slug']) + ['category_id' => $category->id];

        if (array_key_exists('instructions', $attributes) && $attributes['instructions'] === null) {
            $attributes['instructions'] = '';
        }

        // Perform initial creation outside the lock transaction so a unique-key retry sees the committed row.
        $skill = Skill::firstOrCreate(['slug' => $data['slug']], $attributes + ['instructions' => '']);
        $created = $skill->wasRecentlyCreated;

        if (! $created) {
            $skill = DB::transaction(function () use ($skill, $attributes): Skill {
                $current = Skill::whereKey($skill->id)->lockForUpdate()->firstOrFail();
                abort_if($current->is_published, 409, 'Published skills must be edited in Filament.');
                $current->fill($attributes);
                if ($current->isDirty('source_url') && ! array_key_exists('source_markdown', $attributes)) {
                    $current->source_markdown = null;
                }
                $current->save();

                return $current;
            }, attempts: 3);
        }

        return (new SkillResource($skill->refresh()->load('category')))->response()
            ->setStatusCode($created ? 201 : 200)
            ->header('Location', route('api.v1.skills.show', $skill->slug));
    }
}
