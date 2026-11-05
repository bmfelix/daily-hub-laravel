<button class="live-button" type="button" aria-pressed="false" aria-label="Listen live to {{ $station->name }}" data-live data-src="{{ $station->streamUrl() }}">
  <span class="when-stopped">Listen live</span>
  <span class="when-playing">Stop</span>
</button>
