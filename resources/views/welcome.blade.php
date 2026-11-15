@extends('layouts.public')

@php
  $pageTitle = 'skillshunter.net — Curated AI Skills & Community Reviews';
  $pageDescription = 'Find AI agent skills by topic, read original SKILL.md instructions, and share what worked in your workflow.';
  $canonicalUrl = route('welcome', array_filter([
    'q' => $search,
    'category' => $category,
    'page' => $skills->currentPage() > 1 ? $skills->currentPage() : null,
  ], fn ($value) => filled($value)));
@endphp

@section('title', $pageTitle)

@push('meta')
  <meta name="description" content="{{ $pageDescription }}">
  <link rel="canonical" href="{{ $canonicalUrl }}">
  <meta property="og:type" content="website">
  <meta property="og:title" content="{{ $pageTitle }}">
  <meta property="og:description" content="{{ $pageDescription }}">
  <meta property="og:url" content="{{ $canonicalUrl }}">
  <meta name="google-site-verification" content="CoBJLXA_fglP9V5yZuou4Ai-DAyBYPXONxZjgvPmhis" />
@endpush

@section('status-message')
{{ $catalogStatusMessage }}
@endsection

@section('status-meta')
{{ $publishedCount }} PUBLISHED SKILLS
@endsection

@section('navigation')
<a href="#skill-directory" class="text-ink hover:text-emeraldTrust transition-colors">Skill Directory</a>
        <a href="#editorial-standards" class="hover:text-emeraldTrust transition-colors">How We List Skills</a>
        @if ($feed->isNotEmpty())
        <a href="#community-feed" class="hover:text-emeraldTrust transition-colors">Latest Reviews</a>
        @endif
@endsection

@section('header-actions')
<a href="#suggest-skill" class="text-sm font-medium text-ink hover:text-emeraldTrust px-3 py-1.5 transition-colors">+ Suggest a Skill</a>
        <a href="{{ $reviewSkills->isNotEmpty() ? '#share-review' : '#suggest-skill' }}" class="bg-ink text-paper hover:bg-emeraldTrust text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm">Share Your Experience</a>
@endsection

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6">@include('catalog.form-feedback')</div>
  <section class="max-w-6xl mx-auto px-4 sm:px-6 pt-12 pb-10 border-b border-borderline">
    <div class="max-w-3xl">
      <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-borderline bg-white text-xs font-medium text-emeraldTrust mb-5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Independent AI Skill & Workflow Library
      </div>
      <h1 class="font-serif text-4xl sm:text-5xl font-medium tracking-tight text-ink leading-[1.12] mb-4">
        Find AI skills for <span class="italic text-emeraldTrust">your workflow</span>.
      </h1>
      <p class="text-base sm:text-lg text-muted leading-relaxed mb-8">
        Browse agent skills, compare use cases, and open the original source. Share your results after trying a skill.
      </p>
      <form action="{{ route('welcome') }}" method="GET" aria-label="Filter skills" class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
          <svg class="w-4 h-4 text-muted absolute left-3.5 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input
            id="searchInput" name="q" value="{{ $search }}" maxlength="100"
            aria-label="Search skills"
            type="text"
            placeholder="Search skills, e.g. code review, SEO content, financial spreadsheets, or Claude Artifacts..."
            class="w-full pl-10 pr-4 py-3 bg-white border border-borderline rounded-xl text-sm focus:outline-none focus:border-emeraldTrust transition-colors"
          />
        </div>
        <select id="categoryFilter" name="category" aria-label="Filter by category" class="px-4 py-3 bg-white border border-borderline rounded-xl text-sm">
          <option value="">All Categories</option>
          @foreach ($categories as $option)
            <option value="{{ $option->slug }}" @selected($option->slug === $category)>{{ $option->name }}</option>
          @endforeach
        </select>
        <button class="px-4 py-3 bg-ink text-paper rounded-xl text-sm">Search</button>
      </form>
    </div>
  </section>
  <div id="skill-directory" class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
      <div class="lg:col-span-8 space-y-6">
        <div class="flex items-center justify-between border-b border-borderline pb-3">
          <h2 class="font-serif text-2xl font-medium text-ink">Skill Directory</h2>
          <span id="resultCount" class="text-xs font-mono text-muted">{{ $skills->total() }} SKILLS FOUND</span>
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
              <a href="{{ route('detail', $skill->slug) }}#system-instructions" class="px-3 py-1.5 text-xs bg-[#F3F1EC] rounded-lg">View Instructions</a>
            </div>

            <a href="{{ route('detail', $skill->slug) }}#write-review" class="text-xs font-semibold text-emeraldTrust hover:underline">+ Add a Review</a>
          </div>
        </article>
          @empty
            <p class="text-muted">No skills found.</p>
          @endforelse
          {{ $skills->links() }}
        </div>
      </div>
      <aside class="lg:col-span-4 space-y-8">
        @if ($feed->isNotEmpty())
        <div id="community-feed" class="grid gap-4 bg-white border border-borderline rounded-2xl p-5 shadow-[0_2px_10px_rgba(0,0,0,0.02)]">
          <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2">
            <h3 class="font-serif text-lg font-medium text-ink">Latest Field Reports</h3>
            <span class="inline-flex items-center gap-1.5 text-[11px] font-mono text-emeraldTrust bg-emeraldTrust/10 px-2 py-0.5 rounded-full">
              <span class="w-1.5 h-1.5 rounded-full bg-emeraldTrust animate-pulse"></span> COMMUNITY FEED
            </span>
          </div>

          <div class="space-y-4 divide-y divide-borderline">
            @foreach ($feed as $review)
              <article class="pt-3 first:pt-0 text-xs">
                <div class="flex justify-between gap-2">
                  <strong>{{ $review->author }}</strong>
                  <span class="text-muted">{{ $review->created_at->diffForHumans() }}</span>
                </div>
                <a class="text-emeraldTrust" href="{{ route('detail', $review->skill->slug) }}#reviews">{{ $review->skill->title }}</a>
                <p class="mt-1 text-muted">{{ $review->body }}</p>
              </article>
            @endforeach
          </div>

          <a href="{{ $reviewSkills->isNotEmpty() ? '#share-review' : '#suggest-skill' }}" class="block py-2.5 px-4 text-center border border-ink text-ink hover:bg-ink hover:text-paper text-xs font-semibold rounded-xl transition-colors">+ Share Your Experience</a>
        </div>
        @endif
        <div id="editorial-standards" class="bg-[#F3F1EC] border border-borderline rounded-2xl p-5">
          <h4 class="font-serif text-base font-semibold text-ink mb-2">How We List Skills</h4>
          <ul class="space-y-2.5 text-xs text-muted leading-relaxed">
            <li class="flex items-start gap-2">
              <span class="text-emeraldTrust font-bold">✓</span>
              <span>Our automated worker finds skills from public sources and drafts catalog pages.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emeraldTrust font-bold">✓</span>
              <span>Catalog editors record listing approval when they choose to include a skill. Linked source files provide the original instructions when available.</span>
            </li>
            <li class="flex items-start gap-2">
              <span class="text-emeraldTrust font-bold">✓</span>
              <span>Readers can submit first-hand results. Reviews appear after moderation.</span>
            </li>
          </ul>
        </div>

      </aside>
    </div>
  </div>
  <section class="max-w-6xl mx-auto px-4 sm:px-6 pb-12 grid gap-6 md:grid-cols-2">
    @if ($reviewSkills->isNotEmpty())
      <div id="share-review" class="bg-white border border-borderline rounded-2xl p-6 scroll-mt-20">
        <h2 id="catalog-review-heading" class="font-serif text-2xl mb-3">Share Your Experience</h2>
        @include('catalog.review-form', ['reviewTarget' => null])
      </div>
    @endif
    <div id="suggest-skill" class="bg-white border border-borderline rounded-2xl p-6 scroll-mt-20">
      <h2 id="suggest-heading" class="font-serif text-2xl mb-3">Suggest a Skill</h2>
      <form method="POST" action="{{ route('suggestions.store') }}" aria-labelledby="suggest-heading" class="grid gap-3">
        @csrf
        <label for="suggest-title">Skill title</label>
        <input id="suggest-title" name="title" value="{{ old('title') }}" required maxlength="160" class="px-3 py-2 border border-borderline rounded-lg">
        <label for="suggest-author">Your name</label>
        <input id="suggest-author" name="author" value="{{ old('author') }}" required maxlength="100" class="px-3 py-2 border border-borderline rounded-lg">
        <label for="suggest-body">What should this skill help with?</label>
        <textarea id="suggest-body" name="body" required maxlength="3000" rows="3" class="px-3 py-2 border border-borderline rounded-lg">{{ old('body') }}</textarea>
        <p class="text-xs text-muted">We use your submission to evaluate the suggested skill. See the <a class="text-emeraldTrust underline" href="{{ route('privacy') }}">Privacy Notice</a>.</p>
        <button class="justify-self-start px-4 py-2 bg-ink text-paper rounded-lg">Submit Suggestion</button>
      </form>
    </div>
  </section>
@endsection
