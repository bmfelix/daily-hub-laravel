<div class="station-cards" data-live-card="radio">
  @foreach ($stations as $item)
    @php
      $track = $item['tracks'][0] ?? null;
      $art = $track ? ($track['image'] ?: ($track['song']->artwork_url ?? '')) : '';
    @endphp
    <div class="card card-station">
      <h2><a class="card-link" href="{{ route('modules.radio') }}">{{ $item['station']->name }}</a><span class="meta">{{ $item['station']->frequency }}</span></h2>
      <div class="now-playing">
        <span class="art">@if ($art)<img src="{{ $art }}" alt="" width="150" height="150">@endif</span>
        <div>
          <span class="on-air @if ($track['playing'] ?? false) is-live @endif">{{ $track ? ($track['playing'] ? 'On air' : 'Last played') : 'Unavailable' }}</span>
          @if ($track)
            <strong>{{ $track['title'] }}</strong>
            <a class="artist-link" href="{{ route('artists.lookup', ['name' => $track['artist']]) }}">{{ $track['artist'] }}</a>
          @else
            <span>{{ $item['error'] ?? 'No songs yet.' }}</span>
          @endif
          <div class="card-actions">
            @include('partials.live-button', ['station' => $item['station']])
            @if (isset($track['song']) && $track['song']->hasPreview())
              @include('partials.preview-button', ['song' => $track['song']])
              <span class="meta">Song preview</span>
            @endif
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>
