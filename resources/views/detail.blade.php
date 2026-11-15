@extends('layouts.public')

@php
  $hasSource = filled($skill->source_url);
  $sourceUrl = $hasSource && \Illuminate\Support\Str::isUrl($skill->source_url, ['http', 'https']) ? $skill->source_url : null;
  $instructions = $skill->publicInstructions();
  $pageTitle = $skill->seo_title ?: $skill->title.' — skillshunter.net';
  $pageDescription = $skill->seo_description ?: \Illuminate\Support\Str::limit($skill->summary, 170);
  $canonicalUrl = route('detail', $skill->slug);
  $editorNote = trim($skill->editor_note ?? '');
  $showEditorNote = $editorNote !== '' && $editorNote !== trim($skill->summary);
@endphp

@section('title')
{{ $pageTitle }}
@endsection

@push('meta')
  <meta name="description" content="{{ $pageDescription }}">
  <link rel="canonical" href="{{ $canonicalUrl }}">
  <meta property="og:type" content="website">
  <meta property="og:title" content="{{ $pageTitle }}">
  <meta property="og:description" content="{{ $pageDescription }}">
  <meta property="og:url" content="{{ $canonicalUrl }}">
@endpush

@push('meta')
  @if (filled($formattedSource))
    <style>
      .skill-markdown > * + * { margin-block-start: 1rem; }
      .skill-markdown :is(h3, h4, h5, h6) { font-family: Newsreader, Georgia, serif; font-size: 1.25rem; font-weight: 600; line-height: 1.25; }
      .skill-markdown :is(ul, ol) { padding-inline-start: 1.5rem; }
      .skill-markdown ul { list-style: disc; }
      .skill-markdown ol { list-style: decimal; }
      .skill-markdown li + li { margin-block-start: .25rem; }
      .skill-markdown li > :is(ul, ol) { margin-block: .25rem; }
      .skill-markdown a { color: #0F5C4C; text-decoration: underline; }
      .skill-markdown pre { background: #F3F1EC; border-radius: .5rem; padding: .75rem; white-space: pre-wrap; }
      .skill-markdown :not(pre) > code { background: #F3F1EC; border-radius: .25rem; padding-inline: .25rem; }
      .skill-markdown blockquote { border-inline-start: .1875rem solid #E5E2DC; padding-inline-start: .75rem; }
      .skill-markdown table { border-collapse: collapse; table-layout: fixed; width: 100%; }
      .skill-markdown :is(th, td) { border: 1px solid #E5E2DC; padding: .5rem; text-align: start; vertical-align: top; }
      .skill-markdown th { background: #F3F1EC; }
    </style>
  @endif
@endpush

@section('status-message')
{{ $skill->verified_at ? 'Approved catalog listing' : 'AI skill directory' }}
@endsection

@section('status-meta')
SKILL #{{ $skill->id }} @if ($skill->version) • VERSION {{ $skill->version }} @endif
@endsection

@section('navigation')
<a href="{{ route('welcome') }}" class="hover:text-emeraldTrust transition-colors">← All Skills</a>
        <a href="#system-instructions" class="hover:text-emeraldTrust transition-colors">System Instructions</a>
        <a href="#reviews" class="hover:text-emeraldTrust transition-colors">Community Reviews @if ($skill->approved_reviews_count) ({{ $skill->approved_reviews_count }}) @endif</a>
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
            Listing Approved
          </span>
          <span class="text-xs font-mono text-muted px-2.5 py-1 rounded-md border border-borderline bg-white">
            Approved on: {{ $skill->verified_at->format('F j, Y') }}
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
        @if ($skill->approved_reviews_count)
        <div class="text-center px-3 border-r border-borderline">
          <div id="heroReviewCount" class="font-serif text-3xl font-semibold text-emeraldTrust">{{ $skill->approved_reviews_count }}</div>
          <div class="text-[11px] font-mono text-muted mt-0.5">REVIEWS</div>
        </div>
        @endif
        <div class="text-center px-2">
          <form method="POST" action="{{ route('skills.vote', $skill->slug) }}">
            @csrf
            <button class="flex flex-col items-center group">
            <span class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-borderline group-hover:border-emeraldTrust group-hover:text-emeraldTrust transition-colors">
              ▲ Helpful @if ($skill->votes_count) · {{ $skill->votes_count }} @endif
            </span>
          </button>
          </form>
        </div>
      </div>
    </div>
  </section>
  <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
      <div class="lg:col-span-8 min-w-0 space-y-10">
        @if ($showEditorNote || filled($skill->use_cases))
          <section class="bg-white border border-borderline rounded-2xl p-6 sm:p-7 space-y-4">
            @if ($showEditorNote)
              <h2 class="font-serif text-xl text-ink">Editor's Note</h2>
              <p class="text-sm text-ink/90 leading-relaxed">{{ $editorNote }}</p>
            @endif
            @if (filled($skill->use_cases))
              @if ($showEditorNote)
                <h3 class="font-serif text-lg text-ink">When to Use It</h3>
              @else
                <h2 class="font-serif text-xl text-ink">When to Use It</h2>
              @endif
              <ul class="list-disc pl-5 space-y-1.5 text-sm text-ink/85">
                @foreach ($skill->use_cases as $item)
                  <li>{{ $item }}</li>
                @endforeach
              </ul>
            @endif
          </section>
        @endif
        @if ($sampleInput !== null)
          <section aria-labelledby="sample-prompt-heading" class="bg-white border border-borderline rounded-2xl p-6 space-y-4">
            <h2 id="sample-prompt-heading" class="font-serif text-xl text-ink">Try this prompt</h2>
            <pre class="whitespace-pre-wrap break-words rounded-xl bg-[#F3F1EC] p-4 text-sm"><code>{{ $sampleInput }}</code></pre>
            @if ($sampleOutput !== null)
              <h3 class="font-semibold text-sm">Example output from the source</h3>
              <pre class="whitespace-pre-wrap break-words rounded-xl bg-[#F3F1EC] p-4 text-sm"><code>{{ $sampleOutput }}</code></pre>
            @endif
          </section>
        @endif
        <section id="system-instructions" class="space-y-3">
          <h2 class="font-serif text-2xl font-medium text-ink">{{ $hasSource ? 'SKILL.md' : 'System Instructions' }}</h2>
          @if ($hasSource && $sourceUrl)
            <div class="space-y-1 text-xs">
              <p class="font-semibold text-ink">Source URL</p>
              <a href="{{ $sourceUrl }}" class="block [overflow-wrap:anywhere] text-emeraldTrust underline underline-offset-4">{{ $sourceUrl }}</a>
              @if ($skill->slug === 'github-vercel-labs-agent-skills-skills-react-best-practices-4d22a63f8b8e')
                <a href="https://github.com/vercel-labs/agent-skills/blob/main/skills/react-best-practices/AGENTS.md" class="inline-block text-emeraldTrust underline underline-offset-4">Read the full rules and code examples</a>
              @elseif ($skill->slug === 'github-vercel-labs-agent-skills-skills-writing-guidelines-689f91e862ca' && str_contains($instructions ?? '', 'https://raw.githubusercontent.com/vercel-labs/writing-guidelines/main/command.md'))
                <a href="https://github.com/vercel-labs/writing-guidelines/blob/main/command.md" class="inline-block text-emeraldTrust underline underline-offset-4">Read the full writing guidelines</a>
              @endif
            </div>
          @endif
          @if (filled($instructions))
            <div class="flex flex-wrap items-center gap-2">
              <a href="{{ route('skills.download', $skill->slug) }}" class="px-3 py-2 text-xs font-medium border border-borderline bg-white rounded-lg">↓ Download .md</a>
              <button type="button" id="copyInstructions" command="--copy" commandfor="skillPromptText" class="px-4 py-2 text-xs font-semibold bg-ink text-paper hover:bg-emeraldTrust rounded-lg">
                Copy Instructions
              </button>
              <span id="copyStatus" role="status" class="text-xs text-muted"></span>
            </div>
            @if (filled($formattedSource))
              <div class="skill-markdown bg-white border border-borderline rounded-2xl p-5 sm:p-7 text-sm leading-relaxed [overflow-wrap:anywhere]">
                {!! $formattedSource !!}
              </div>
              <details class="rounded-xl border border-borderline bg-white">
                <summary class="cursor-pointer px-4 py-3 text-sm font-medium">View original Markdown</summary>
                <pre class="bg-[#181817] text-[#EAE7DF] rounded-b-xl p-5 font-mono text-xs leading-relaxed whitespace-pre-wrap break-words"><code id="skillPromptText">{{ $instructions }}</code></pre>
              </details>
            @else
            <pre class="bg-[#181817] text-[#EAE7DF] rounded-2xl p-5 sm:p-6 font-mono text-xs sm:text-[13px] leading-relaxed whitespace-pre-wrap break-words"><code id="skillPromptText">{{ $instructions }}</code></pre>
            @endif
          @endif
        </section>
        <section id="reviews" class="space-y-6 pt-2">
          <div class="flex flex-wrap items-end justify-between gap-2 border-b border-borderline pb-3">
            <div>
              <h2 class="font-serif text-2xl font-medium text-ink">Community Reviews & Field Notes</h2>
              <p class="text-xs text-muted">Tried this skill on your own project? Share the model you used and the results you got.</p>
            </div>
            @if ($skill->approved_reviews_count)
            <form method="GET" action="{{ route('detail', $skill->slug) }}#reviews" aria-label="Filter reviews" class="flex gap-2">
              <select name="verdict" aria-label="Filter reviews by verdict" class="px-3 py-1.5 border border-borderline rounded-lg text-xs">
                <option value="">All Reviews</option>
                @foreach (\App\Models\Review::VERDICTS as $option)
                  <option @selected($verdict === $option)>{{ $option }}</option>
                @endforeach
              </select>
              <button class="px-3 py-1.5 border border-borderline rounded-lg text-xs">Filter</button>
            </form>
            @endif
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
            @if ($skill->approved_reviews_count)
              <p class="text-sm text-muted">No reviews match this filter.</p>
            @endif
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
        @if ($related->isNotEmpty())
          <div class="bg-white border border-borderline rounded-2xl p-5">
            <h3 class="font-serif text-base font-semibold mb-3">Related Skills</h3>
            @foreach ($related as $item)
              <a href="{{ route('detail', $item->slug) }}" class="block py-2 text-xs hover:text-emeraldTrust">{{ $item->title }} @if ($item->approved_reviews_count) · {{ $item->approved_reviews_count }} reviews @endif</a>
            @endforeach
          </div>
        @endif
      </aside>
    </div>
  </div>
@endsection

@push('scripts')
  <script>
    (() => {
      const button = document.getElementById('copyInstructions');
      if (!button) return;

      const status = document.getElementById('copyStatus');
      let timeout;
      let lastMessage = '';

      async function copyInstructions(target) {
        let message;
        try {
          await navigator.clipboard.writeText(target.textContent);
          message = 'Copied';
        } catch {
          message = 'Copy failed. Use Download .md to save the instructions.';
        }
        if (message === lastMessage) message += '\u00a0';
        lastMessage = message;
        status.textContent = message;
        clearTimeout(timeout);
        timeout = setTimeout(() => { status.textContent = ''; }, 2500);
      }

      if ('CommandEvent' in window && 'commandForElement' in button) {
        document.getElementById(button.getAttribute('commandfor')).addEventListener('command', (event) => {
          if (event.command === '--copy' && event.source === button) copyInstructions(event.currentTarget);
        });
      } else {
        button.addEventListener('click', () => copyInstructions(document.getElementById(button.getAttribute('commandfor'))));
      }
    })();
  </script>
@endpush
