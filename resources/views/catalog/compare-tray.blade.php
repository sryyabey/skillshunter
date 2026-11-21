<aside id="compare-tray" data-url="{{ route('skills.compare') }}" aria-label="Selected skills" hidden>
  <div class="compare-tray-inner">
    <span class="font-medium">Compare <span data-compare-count>0</span>/3</span>
    <div data-compare-items class="compare-items"></div>
    <a data-compare-open class="compare-action" href="{{ route('skills.compare') }}">Compare skills</a>
    <button type="button" data-compare-clear class="compare-secondary">Clear</button>
  </div>
</aside>
<p id="compare-feedback" role="status" class="sr-only" aria-live="polite"></p>
