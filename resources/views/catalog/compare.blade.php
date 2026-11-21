@extends('layouts.public')
@section('title', 'Compare AI Skills — SkillsHunter')
@push('meta')
<meta name="robots" content="noindex,follow">
@endpush
@section('content')
<section class="max-w-6xl mx-auto px-4 sm:px-6 py-10" id="skill-comparison" data-selection="{{ $skills->map(fn ($skill) => $skill->only('slug', 'title'))->toJson() }}">
  <a href="{{ route('welcome') }}" class="text-sm text-emeraldTrust">Back to directory</a>
  <div class="flex flex-wrap items-center justify-between gap-4 mt-6 mb-6">
    <div><h1 class="font-serif text-4xl mb-2">Find your best fit.</h1><p class="text-muted">Compare up to three skills. Click a title to expand its column.</p></div>
    <button type="button" data-compare-share class="compare-secondary">Copy comparison link</button>
  </div>
  @if (count($slugs) !== $skills->count())
    <p class="mb-4 text-muted">Some selected skills are no longer available. Only published skills are shown.</p>
  @endif
  @if ($skills->count() >= 2)
    <label class="flex items-center gap-2 mb-4 text-sm"><input type="checkbox" data-compare-differences class="accent-emeraldTrust"> Only show differences</label>
    <div class="comparison-scroll" role="region" aria-label="Skill comparison" tabindex="0">
      <table class="comparison-table">
        <caption class="sr-only">Selected skills compared by use cases, compatibility, setup and examples</caption>
        <colgroup><col class="comparison-label-col">@foreach ($skills as $skill)<col data-compare-col>@endforeach</colgroup>
        <thead><tr><th scope="col">Your options</th>
        @foreach ($skills as $skill)
          <th scope="col" data-column="{{ $loop->index }}">
            <div class="flex justify-between gap-2 mb-3"><span class="text-xs text-emeraldTrust">{{ $skill->category->name }}</span><button type="button" data-compare-remove="{{ $skill->slug }}" aria-label="Remove {{ $skill->title }}">×</button></div>
            <button type="button" data-compare-focus="{{ $loop->index }}" aria-pressed="false" class="comparison-title font-serif">{{ $skill->title }}</button>
            <p class="text-xs text-muted mt-3">{{ $skill->verified_at ? 'Listing approved · '.$skill->verified_at->format('M j, Y') : 'Approval date not specified' }}</p>
          </th>
        @endforeach
        </tr></thead>
        <tbody>
        @foreach ($rows as $row)
          <tr data-different="{{ $row['different'] ? 'true' : 'false' }}">
            <th scope="row">{{ $row['label'] }}</th>
            @foreach ($row['values'] as $value)
              <td data-column="{{ $loop->index }}">@if (filled($value))<div class="comparison-value">{{ $value }}</div>@else<span class="text-muted">Not specified</span>@endif</td>
            @endforeach
          </tr>
        @endforeach
        <tr><th scope="row">Community reviews</th>@foreach ($skills as $skill)<td data-column="{{ $loop->index }}">{{ $skill->approved_reviews_count }} reviews</td>@endforeach</tr>
        <tr><th scope="row">Next step</th>@foreach ($skills as $skill)<td data-column="{{ $loop->index }}"><a class="compare-action" href="{{ route('detail', $skill->slug) }}">Explore skill</a></td>@endforeach</tr>
        </tbody>
      </table>
    </div>
  @else
    <div class="border border-borderline bg-white rounded-xl p-6"><p class="mb-4">Select at least two published skills to compare.</p><a href="{{ route('welcome') }}" class="compare-action">Browse skills</a></div>
  @endif
  <p class="text-sm text-muted mt-4" data-comparison-status role="status" aria-live="polite">Selections are saved in this browser. No account needed.</p>
</section>
@endsection
