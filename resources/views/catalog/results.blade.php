@php
  $categoryName = $categories->firstWhere('slug', $category)?->name ?? $category;
  $allCategoriesUrl = route('welcome', $search !== '' ? ['q' => $search] : []);
@endphp

<div class="space-y-3 border-b border-borderline pb-3">
  <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
    <h2 tabindex="-1" class="min-w-0 break-words font-serif text-2xl font-medium text-ink">{{ $search !== '' ? 'Results for “'.$search.'”' : 'Skill Directory' }}</h2>
    <span id="resultCount" class="text-xs font-mono text-muted">{{ $skills->total() }} {{ $skills->total() === 1 ? 'SKILL' : 'SKILLS' }} FOUND</span>
  </div>
  @if ($search !== '' || $category !== '')
  <ul aria-label="Active filters" class="flex flex-wrap gap-2">
    @if ($search !== '')
    <li class="min-w-0"><a href="{{ route('welcome', $category !== '' ? ['category' => $category] : []) }}" data-catalog-link aria-label="Remove search query: {{ $search }}" class="inline-flex min-h-12 max-w-full items-center gap-2 rounded-full border border-borderline bg-white px-3 text-sm hover:border-emeraldTrust">
      <span class="truncate">{{ $search }}</span><span aria-hidden="true">×</span>
    </a></li>
    @endif
    @if ($category !== '')
    <li class="min-w-0"><a href="{{ $allCategoriesUrl }}" data-catalog-link aria-label="Remove category filter: {{ $categoryName }}" class="inline-flex min-h-12 max-w-full items-center gap-2 rounded-full border border-borderline bg-white px-3 text-sm hover:border-emeraldTrust">
      <span class="truncate">{{ $categoryName }}</span><span aria-hidden="true">×</span>
    </a></li>
    @endif
  </ul>
  @endif
</div>

<div class="space-y-5">
  @forelse ($skills as $skill)
<article class="bg-white border border-borderline hover:border-ink/40 rounded-2xl p-6 transition-all shadow-[0_2px_8px_rgba(0,0,0,0.02)]">
  <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
    <div class="flex items-center gap-2">
      <span class="text-xs font-medium px-2.5 py-0.5 rounded-md bg-[#F3F1EC] text-ink">
        {{ $skill->category->name }}
      </span>
      @if ($skill->verified_at)
      <span class="inline-flex items-center gap-1 text-xs font-medium text-emeraldTrust">
        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        Listing Approved
      </span>
      @endif
    </div>
    @if ($skill->approved_reviews_count)
    <div class="flex items-center gap-3 text-xs text-muted font-mono">
      <span>{{ $skill->approved_reviews_count }} reviews</span>
    </div>
    @endif
  </div>
  <h3 class="font-serif text-2xl font-medium text-ink mb-2">
    <a href="{{ route('detail', $skill->slug) }}" class="hover:text-emeraldTrust">{{ $skill->title }}</a>
  </h3>
  <p class="text-sm text-muted leading-relaxed mb-4">
    <span class="font-semibold text-ink">Overview:</span> {{ $skill->editor_note ?? $skill->summary }}
  </p>
  @if ($skill->latestReview)
  <div class="bg-amberNote/60 border border-amber-200/70 rounded-xl p-3.5 mb-5">
    <div class="flex items-center justify-between text-xs mb-1">
      <span class="font-semibold text-ink">{{ $skill->latestReview->author }}</span>
      <span class="font-mono text-[11px] px-2 py-0.5 bg-white rounded border border-amber-200 text-ink/80">
        {{ $skill->latestReview->model }} • {{ $skill->latestReview->verdict }}
      </span>
    </div>
    <p class="text-xs text-ink/85 italic">“{{ $skill->latestReview->body }}”</p>
  </div>
  @endif
  <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-borderline">
    <div class="flex items-center gap-2">
      <form method="POST" action="{{ route('skills.vote', $skill->slug) }}">
        @csrf
        <button class="px-3 py-1.5 text-xs border border-borderline rounded-lg">▲ Helpful @if ($skill->votes_count) · {{ $skill->votes_count }} @endif</button>
      </form>
      @include('catalog.compare-button')
      <a href="{{ route('detail', $skill->slug) }}#system-instructions" class="px-3 py-1.5 text-xs bg-[#F3F1EC] rounded-lg">View Instructions</a>
    </div>

    <a href="{{ route('detail', $skill->slug) }}#write-review" class="text-xs font-semibold text-emeraldTrust hover:underline">+ Add a Review</a>
  </div>
</article>
  @empty
    <div class="space-y-3 rounded-2xl border border-borderline bg-white p-6">
      @if ($skills->total() > 0)
        <p class="text-muted">There are no skills on this page.</p>
        <a data-catalog-link href="{{ $skills->url(1) }}" class="font-medium text-emeraldTrust underline">Go to the first page</a>
      @else
        <p class="font-medium text-ink">No skills found{{ $category !== '' ? ' in '.$categoryName : '' }}.</p>
        @if ($search !== '')
          <p class="text-sm text-muted">Try a shorter search or a different term.</p>
        @endif
        @if ($category !== '')
          <a data-catalog-link href="{{ $allCategoriesUrl }}" class="inline-flex min-h-12 items-center text-sm font-medium text-emeraldTrust underline">Search all categories</a>
        @endif
      @endif
    </div>
    @if ($relatedSkills->isNotEmpty() || $suggestedSkills->isNotEmpty())
      <div class="space-y-2 rounded-2xl border border-borderline bg-white p-6">
        <h3 class="font-serif text-xl font-medium text-ink">{{ $relatedSkills->isNotEmpty() ? 'Related skills' : 'Explore the catalog' }}</h3>
        <ul class="divide-y divide-borderline">
          @foreach ($relatedSkills->isNotEmpty() ? $relatedSkills : $suggestedSkills as $suggestion)
          <li><a href="{{ route('detail', $suggestion->slug) }}" class="inline-flex min-h-12 items-center text-sm font-medium text-emeraldTrust hover:underline">{{ $suggestion->title }}</a></li>
          @endforeach
        </ul>
      </div>
    @endif
  @endforelse
  <div data-catalog-pagination>{{ $skills->links('catalog.pagination') }}</div>
</div>
