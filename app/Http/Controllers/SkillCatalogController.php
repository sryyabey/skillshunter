<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Review;
use App\Models\Skill;
use App\Models\SkillSuggestion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SkillCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->limit(100, '')->toString());
        $category = $request->string('category')->toString();

        $skills = Skill::published()
            ->with(['category', 'latestReview'])
            ->withCount(['approvedReviews', 'votes'])
            ->when($category, fn (Builder $query) => $query->whereRelation('category', 'slug', $category))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->whereLike('title', '%'.$search.'%')
                        ->orWhereLike('summary', '%'.$search.'%')
                        ->orWhereHas('category', fn (Builder $query) => $query->whereLike('name', '%'.$search.'%'));
                });
            })
            ->orderByDesc('verified_at')->orderByDesc('id')
            ->paginate(12)->withQueryString();

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
