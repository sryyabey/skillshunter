@extends('layouts.public')

@section('title')
{{ $skill->seo_title ?: $skill->title.' — skillshunter.net' }}
@endsection

@push('meta')
  <meta name="description" content="{{ $skill->seo_description ?: \Illuminate\Support\Str::limit($skill->summary, 170) }}">
@endpush

@section('status-message')
{{ $skill->verified_at ? 'Editorially verified skill' : 'AI skill directory' }}
@endsection

@section('status-meta')
SKILL #{{ $skill->id }} @if ($skill->version) • VERSION {{ $skill->version }} @endif
@endsection

@section('navigation')
<a href="{{ route('welcome') }}" class="hover:text-emeraldTrust transition-colors">← All Skills</a>
        <a href="#system-instructions" class="hover:text-emeraldTrust transition-colors">System Instructions</a>
        @if ($skill->sample_input || $skill->sample_output)
        <a href="#test-evidence" class="hover:text-emeraldTrust transition-colors">Test Evidence & Sample Output</a>
        @endif
        <a href="#reviews" class="hover:text-emeraldTrust transition-colors">Community Reviews (<span id="navReviewCount">{{ $skill->approved_reviews_count }}</span>)</a>
@endsection

@section('header-actions')
<a href="#write-review" class="bg-ink text-paper hover:bg-emeraldTrust text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm">
          Write a Review
        </a>
@endsection

@section('content')
<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-8 pb-8 border-b border-borderline">
    <div class="flex items-center gap-2 text-xs text-muted mb-5">
      <a href="{{ route('welcome') }}" class="hover:text-ink">Home</a>
      <span>/</span>
      <a href="{{ route('welcome', ['category' => $skill->category->slug]) }}#skill-directory" class="hover:text-ink">{{ $skill->category->name }}</a>
      <span>/</span>
      <span class="text-ink font-medium">{{ $skill->title }}</span>
    </div>

    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
      <div class="max-w-3xl">
        <div class="flex flex-wrap items-center gap-2.5 mb-3">
          @if ($skill->verified_at)
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-emeraldTrust/10 text-emeraldTrust text-xs font-semibold">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
            Editor Verified Skill
          </span>
          <span class="text-xs font-mono text-muted px-2.5 py-1 rounded-md border border-borderline bg-white">
            Last tested: {{ $skill->verified_at->format('F j, Y') }}
          </span>
          @endif
          @if ($skill->license)
          <span class="text-xs font-mono text-muted px-2.5 py-1 rounded-md border border-borderline bg-white">
            License: {{ $skill->license }}
          </span>
          @endif
        </div>

        <h1 class="font-serif text-3xl sm:text-5xl font-medium tracking-tight text-ink leading-[1.14] mb-4">
          {{ $skill->title }}
        </h1>
        <p class="text-base sm:text-lg text-muted leading-relaxed">
          {{ $skill->summary }}
        </p>
      </div>
      <div class="flex items-center gap-4 bg-white border border-borderline rounded-2xl p-4 shrink-0">
        <div class="text-center px-3 border-r border-borderline">
          <div id="heroReviewCount" class="font-serif text-3xl font-semibold text-emeraldTrust">{{ $skill->approved_reviews_count }}</div>
          <div class="text-[11px] font-mono text-muted mt-0.5">REVIEWS</div>
        </div>
        <div class="text-center px-2">
          <form method="POST" action="{{ route('skills.vote', $skill->slug) }}">
            @csrf
            <button class="flex flex-col items-center group">
            <span class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-borderline group-hover:border-emeraldTrust group-hover:text-emeraldTrust transition-colors">
              ▲ <span id="helpfulCounter">{{ $skill->votes_count }}</span>
            </span>
            <span class="text-[11px] font-mono text-muted mt-1">HELPFUL</span>
          </button>
          </form>
        </div>
      </div>
    </div>
  </section>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
      <div class="lg:col-span-8 space-y-10">
        <section class="bg-white border border-borderline rounded-2xl p-6 sm:p-7">
          <div class="flex items-center gap-3 mb-4 pb-4 border-b border-borderline">
            <div class="w-9 h-9 rounded-full bg-[#F3F1EC] border border-borderline flex items-center justify-center font-serif font-semibold text-ink">
              SH
            </div>
            <div>
              <div class="text-xs font-semibold text-ink">Editorial Review</div>
              <div class="text-[11px] text-muted">Skill Evaluation</div>
            </div>
          </div>

          <p class="text-sm text-ink/90 leading-relaxed mb-5">
            {{ $skill->editor_note ?? $skill->summary }}
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-[#F3F1EC]/70 border border-borderline rounded-xl p-4">
              <h3 class="text-xs font-bold uppercase tracking-wider text-emeraldTrust mb-2">✓ When to Use It</h3>
              <ul class="space-y-1.5 text-xs text-ink/85">
                @forelse ($skill->use_cases ?? [] as $item)
                  <li>• {{ $item }}</li>
                @empty
                  <li>No notes available.</li>
                @endforelse
              </ul>
            </div>
            <div class="bg-amberNote/60 border border-amber-200/80 rounded-xl p-4">
              <h3 class="text-xs font-bold uppercase tracking-wider text-amber-900 mb-2">! Limitations</h3>
              <ul class="space-y-1.5 text-xs text-ink/85">
                @forelse ($skill->limitations ?? [] as $item)
                  <li>• {{ $item }}</li>
                @empty
                  <li>No notes available.</li>
                @endforelse
              </ul>
            </div>
          </div>
        </section>
        <section id="system-instructions" data-skill-title="{{ $skill->title }}" data-skill-slug="{{ $skill->slug }}" class="space-y-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="font-serif text-2xl font-medium text-ink">Skill System Instructions</h2>
              <p class="text-xs text-muted">Paste these instructions into your system message or save them as <code>SKILL.md</code> in the project root.</p>
            </div>
            <div class="flex items-center gap-2">
              <button onclick="downloadSkillMd()" class="px-3 py-2 text-xs font-medium border border-borderline bg-white hover:border-ink rounded-lg transition-colors">
                ↓ Download .md
              </button>
              <button onclick="copyFullPrompt(this)" class="px-4 py-2 text-xs font-semibold bg-ink text-paper hover:bg-emeraldTrust rounded-lg transition-colors">
                Copy Instructions
              </button>
            </div>
          </div>

          <div class="bg-[#181817] text-[#F3F1EC] rounded-2xl p-5 sm:p-6 border border-ink shadow-sm">
            <div class="flex items-center justify-between text-[11px] font-mono text-white/50 pb-3 mb-3 border-b border-white/10">
              <span>SYSTEM_INSTRUCTION.md</span>
              <span></span>
            </div>
            <pre id="skillPromptText" class="font-mono text-xs sm:text-[13px] leading-relaxed whitespace-pre-wrap text-[#EAE7DF]">{{ $skill->instructions }}</pre>
          </div>
        </section>
        @if ($skill->sample_input || $skill->sample_output)
          <section id="test-evidence" class="bg-white border border-borderline rounded-2xl p-6 space-y-4">
            <h2 class="font-serif text-2xl">Test Evidence</h2>
            @if ($skill->sample_output)
              <h3 class="text-sm font-semibold">Skill Output</h3>
              <pre class="p-4 bg-[#F3F1EC] rounded-xl text-xs whitespace-pre-wrap break-words"><code>{{ $skill->sample_output }}</code></pre>
            @endif
            @if ($skill->sample_input)
              <details>
                <summary class="cursor-pointer text-sm font-semibold">Input Code</summary>
                <pre class="mt-3 p-4 bg-[#F3F1EC] rounded-xl text-xs overflow-x-auto"><code>{{ $skill->sample_input }}</code></pre>
              </details>
            @endif
          </section>
        @endif
        <section id="reviews" class="space-y-6 pt-2">
          <div class="flex flex-wrap items-end justify-between gap-2 border-b border-borderline pb-3">
            <div>
              <h2 class="font-serif text-2xl font-medium text-ink">Community Reviews & Field Notes</h2>
              <p class="text-xs text-muted">Tried this skill on your own project? Share the model you used and the results you got.</p>
            </div>
            <form method="GET" action="{{ route('detail', $skill->slug) }}#reviews" aria-label="Filter reviews" class="flex gap-2">
              <select name="verdict" aria-label="Filter reviews by verdict" class="px-3 py-1.5 border border-borderline rounded-lg text-xs">
                <option value="">All Reviews</option>
                @foreach (\App\Models\Review::VERDICTS as $option)
                  <option @selected($verdict === $option)>{{ $option }}</option>
                @endforeach
              </select>
              <button class="px-3 py-1.5 border border-borderline rounded-lg text-xs">Filter</button>
            </form>
          </div>
          <div id="write-review" class="bg-white border border-emeraldTrust/30 rounded-2xl p-5 scroll-mt-20">
            <h3 id="catalog-review-heading" class="font-serif text-xl mb-3">Add Your Review</h3>
            @include('catalog.form-feedback')
            @include('catalog.review-form', ['reviewTarget' => $skill])
          </div>
          @forelse ($reviews as $review)
            <article class="bg-white border border-borderline rounded-2xl p-5">
              <div class="flex flex-wrap justify-between gap-2 text-xs">
                <strong>{{ $review->author }} · {{ $review->created_at->diffForHumans() }}</strong>
                <span class="text-emeraldTrust">{{ $review->model }} · {{ $review->verdict }}</span>
              </div>
              <p class="text-sm my-3">{{ $review->body }}</p>
              <form method="POST" action="{{ route('reviews.vote', $review->id) }}">
                @csrf
                <button class="text-xs text-muted hover:text-emeraldTrust">▲ Helpful review ({{ $review->votes_count }})</button>
              </form>
            </article>
          @empty
            <p class="text-sm text-muted">No reviews to show.</p>
          @endforelse
          {{ $reviews->links() }}
        </section>
      </div>
      <aside class="lg:col-span-4 space-y-6">
        @if ($skill->compatibility)
          <div class="bg-white border border-borderline rounded-2xl p-5">
            <h3 class="font-serif text-lg mb-3">Model Compatibility</h3>
            <div class="space-y-3 text-xs">
              @foreach ($skill->compatibility as $entry)
                <div class="flex items-center justify-between gap-2">
                  <span>{{ $entry['model'] }}</span>
                  <span class="font-mono text-emeraldTrust">{{ $entry['success'] }}% success</span>
                </div>
                <progress class="w-full h-1.5 accent-[#0F5C4C]" value="{{ $entry['success'] }}" max="100" aria-label="{{ $entry['model'] }} success rate"></progress>
              @endforeach
            </div>
          </div>
        @endif
        @if ($skill->setup_tip)
          <div class="bg-[#F3F1EC] border border-borderline rounded-2xl p-5">
            <h3 class="font-serif text-base font-semibold mb-2">Quick Setup Tip</h3>
            <p class="text-xs text-muted leading-relaxed">{{ $skill->setup_tip }}</p>
          </div>
        @endif
        @if ($related->isNotEmpty())
          <div class="bg-white border border-borderline rounded-2xl p-5">
            <h3 class="font-serif text-base font-semibold mb-3">Related Skills</h3>
            @foreach ($related as $item)
              <a href="{{ route('detail', $item->slug) }}" class="block py-2 text-xs hover:text-emeraldTrust">{{ $item->title }} · {{ $item->approved_reviews_count }} reviews</a>
            @endforeach
          </div>
        @endif
      </aside>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    async function copyFullPrompt(btn) {
      const text = document.getElementById('skillPromptText').textContent;
      await navigator.clipboard.writeText(text);
      const orig = btn.textContent;
      btn.textContent = "Copied ✓";
      btn.classList.replace('bg-ink', 'bg-emeraldTrust');
      setTimeout(() => {
        btn.textContent = orig;
        btn.classList.replace('bg-emeraldTrust', 'bg-ink');
      }, 1800);
    }

    function downloadSkillMd() {
      const text = document.getElementById('skillPromptText').textContent;
      const blob = new Blob([`# ${document.getElementById('system-instructions').dataset.skillTitle}\n\n${text}`], { type: 'text/markdown' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = document.getElementById('system-instructions').dataset.skillSlug + '-skill.md';
      a.click();
      URL.revokeObjectURL(url);
    }

  </script>
@endpush
