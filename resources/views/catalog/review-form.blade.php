<form method="POST" action="{{ route('reviews.store') }}" aria-labelledby="catalog-review-heading" class="grid gap-3 text-sm">
  @csrf
  <input type="hidden" name="skill" value="{{ $reviewTarget->slug }}">
  <label for="review-author">Your name or role</label>
  <input id="review-author" name="author" value="{{ old('author') }}" required maxlength="100" class="px-3 py-2 border border-borderline rounded-lg">
  <label for="review-model">Model used</label>
  <input id="review-model" name="model" value="{{ old('model') }}" required maxlength="100" class="px-3 py-2 border border-borderline rounded-lg">
  <label for="review-verdict">Verdict</label>
  <select id="review-verdict" name="verdict" required class="px-3 py-2 border border-borderline rounded-lg">
    @foreach (\App\Models\Review::VERDICTS as $option)
      <option @selected(old('verdict') === $option)>{{ $option }}</option>
    @endforeach
  </select>
  <label for="review-body">What did you test, and what happened?</label>
  <textarea id="review-body" name="body" required maxlength="3000" rows="3" class="px-3 py-2 border border-borderline rounded-lg">{{ old('body') }}</textarea>
  <p class="text-xs text-muted">Approved reviews appear publicly with the name or role you enter. See the <a class="text-emeraldTrust underline" href="{{ route('privacy') }}">Privacy Notice</a>.</p>
  <button class="justify-self-start px-4 py-2 bg-emeraldTrust text-white rounded-lg">Submit Review</button>
</form>
