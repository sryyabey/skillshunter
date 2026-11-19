@if ($paginator->hasPages())
  @php
    $currentPage = $paginator->currentPage();
    $lastPage = $paginator->lastPage();
    $pages = collect([1, $currentPage - 1, $currentPage, $currentPage + 1, $lastPage])
        ->filter(fn ($page) => $page >= 1 && $page <= $lastPage)
        ->unique()->sort()->values();
    $linkClasses = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-borderline bg-white px-3 text-sm font-medium text-ink transition-colors hover:border-emeraldTrust hover:text-emeraldTrust focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emeraldTrust';
    $disabledClasses = 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-borderline px-3 text-sm text-muted';
  @endphp

  <nav aria-label="Skill directory pagination" class="space-y-3 border-t border-borderline pt-5">
    <p class="text-center text-xs leading-relaxed text-muted">
      {{ number_format($paginator->total()) }} skills &middot;
      @if ($paginator->count())
        Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }}
      @else
        Page {{ number_format($currentPage) }}
      @endif
    </p>

    <div class="flex flex-wrap items-center justify-between gap-2 md:justify-center">
      @if ($paginator->onFirstPage())
        <span aria-disabled="true" class="{{ $disabledClasses }}"><span aria-hidden="true" class="mr-1">&larr;</span> Previous</span>
      @else
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $linkClasses }}"><span aria-hidden="true" class="mr-1">&larr;</span> Previous</a>
      @endif

      <span class="text-xs tabular-nums text-muted md:hidden">
        <span class="sr-only">Page </span>{{ number_format($currentPage) }} / {{ number_format($lastPage) }}
      </span>

      <ul class="hidden flex-wrap items-center justify-center gap-1 md:flex">
        @foreach ($pages as $page)
          @if ($loop->index > 0 && $page - $pages[$loop->index - 1] > 1)
            <li><span class="inline-flex min-h-11 items-center px-1 text-muted"><span aria-hidden="true">…</span><span class="sr-only">More pages</span></span></li>
          @endif
          <li>
            @if ($page === $currentPage)
              <span aria-current="page" aria-label="Page {{ $page }}" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-emeraldTrust bg-emeraldTrust px-3 text-sm font-semibold text-white">{{ $page }}</span>
            @else
              <a href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}" class="{{ $linkClasses }}">{{ $page }}</a>
            @endif
          </li>
        @endforeach
      </ul>

      @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $linkClasses }}">Next <span aria-hidden="true" class="ml-1">&rarr;</span></a>
      @else
        <span aria-disabled="true" class="{{ $disabledClasses }}">Next <span aria-hidden="true" class="ml-1">&rarr;</span></span>
      @endif
    </div>
  </nav>
@endif
