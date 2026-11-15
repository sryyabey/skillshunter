<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Review;
use App\Models\Skill;
use App\Models\SkillSuggestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SkillCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->limit(100, '')->toString());
        $category = $request->string('category')->toString();

        $query = Skill::published()
            ->with(['category', 'latestReview'])
            ->withCount(['approvedReviews', 'votes'])
            ->when($category, fn (Builder $query) => $query->whereRelation('category', 'slug', $category))
            ->when($search !== '', fn (Builder $query) => $query->catalogSearch($search))
            ->orderByDesc('verified_at')->orderByDesc('id');

        $skills = $search === '' && $category === ''
            ? $this->browseCatalog($query)
            : $query->paginate(12)->withQueryString();

        return view('welcome', [
            'skills' => $skills,
            'categories' => Category::orderBy('name')->get(),
            'reviewSkills' => Skill::published()->orderBy('title')->get(['slug', 'title']),
            'feed' => Review::approved()->whereHas('skill', fn (Builder $query) => $query->published())
                ->with('skill')->latest()->orderByDesc('id')->limit(5)->get(),
            'publishedCount' => Skill::published()->count(),
            'search' => $search,
            'category' => $category,
        ]);
    }

    private function browseCatalog(Builder $query): LengthAwarePaginator
    {
        $listing = Skill::published()->orderByDesc('verified_at')->orderByDesc('id')->get(['id', 'source_url']);
        $rank = $listing->pluck('id')->flip();
        $ordered = $listing->groupBy(function (Skill $skill): string {
            if (preg_match('~\Ahttps://github\.com/([\w.-]+/[\w.-]+)(?:/|$)~i', $skill->source_url ?? '', $source)) {
                return strtolower($source[1]);
            }

            return 'skill:'.$skill->id;
        })->flatMap(fn ($group) => $group->values()->map(
            fn (Skill $skill, int $position): array => ['id' => $skill->id, 'position' => $position],
        ))->sortBy(fn (array $entry) => [$entry['position'], $rank[$entry['id']]])->pluck('id')->values();

        $page = Paginator::resolveCurrentPage();
        $ids = $ordered->forPage($page, 12)->values();
        $skills = $query->whereKey($ids->all())->get()->sortBy(fn (Skill $skill) => $ids->search($skill->id))->values();

        return (new LengthAwarePaginator($skills, $ordered->count(), 12, $page, [
            'path' => Paginator::resolveCurrentPath(),
        ]))->withQueryString();
    }

    public function show(Request $request, string $slug): View
    {
        $skill = Skill::published()->where('slug', $slug)->with('category')
            ->withCount(['approvedReviews', 'votes'])->firstOrFail();
        $verdict = $request->string('verdict')->toString();

        return view('detail', [
            'skill' => $skill,
            'verdict' => $verdict,
            'reviews' => $skill->approvedReviews()->withCount('votes')
                ->when(in_array($verdict, Review::VERDICTS, true), fn (Builder $query) => $query->where('verdict', $verdict))
                ->latest()->orderByDesc('id')->paginate(12)->withQueryString()->fragment('reviews'),
            'related' => Skill::published()->where('category_id', $skill->category_id)
                ->whereKeyNot($skill->id)->withCount('approvedReviews')->latest()->limit(4)->get(),
        ]);
    }

    public function download(string $slug): StreamedResponse
    {
        $skill = Skill::published()->where('slug', $slug)->firstOrFail();
        $instructions = $skill->publicInstructions();
        abort_unless(filled($instructions), 404);

        return response()->streamDownload(
            static function () use ($instructions): void {
                echo $instructions;
            },
            filled($skill->source_url) ? 'SKILL.md' : $skill->slug.'.md',
            ['Content-Type' => 'text/markdown; charset=UTF-8'],
        );
    }

    public function review(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'skill' => ['required', 'string', 'max:160', Rule::exists('skills', 'slug')->where('is_published', true)],
            'author' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'verdict' => ['required', Rule::in(Review::VERDICTS)],
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $skill = Skill::published()->where('slug', $data['skill'])->firstOrFail();
        unset($data['skill']);
        $skill->reviews()->create($data);

        return redirect()->to(route('detail', $skill->slug).'#reviews')
            ->with('status', 'Your review was submitted for moderation.');
    }

    public function suggest(Request $request): RedirectResponse
    {
        SkillSuggestion::create($request->validate([
            'author' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:3000'],
        ]));

        return to_route('welcome')->with('status', 'Your skill suggestion was received.');
    }

    public function voteSkill(Request $request, string $slug): RedirectResponse
    {
        $skill = Skill::published()->where('slug', $slug)->firstOrFail();

        DB::table('skill_votes')->insertOrIgnore([
            'skill_id' => $skill->id,
            'voter' => hash('sha256', $request->session()->token()),
        ]);

        return back();
    }

    public function voteReview(Request $request, int $id): RedirectResponse
    {
        $review = Review::approved()->whereHas('skill', fn (Builder $query) => $query->published())->findOrFail($id);

        DB::table('review_votes')->insertOrIgnore([
            'review_id' => $review->id,
            'voter' => hash('sha256', $request->session()->token()),
        ]);

        return back();
    }
}
