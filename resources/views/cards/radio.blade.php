<ul class="now-playing-list" data-live-card="radio">
  @foreach ($stations as $item)
    @php($track = $item['tracks'][0] ?? null)
    <li class="now-playing">
      @if ($track && $track['image'])<img src="{{ $track['image'] }}" alt="" width="48" height="48">@endif
      <div>
        <span class="meta">{{ $item['station']->name }} · {{ $track ? ($track['playing'] ? 'Now playing' : 'Last played') : 'Unavailable' }}</span>
        @if ($track)
          <strong>{{ $track['title'] }}</strong>
          <span>{{ $track['artist'] }}</span>
        @else
          <span>{{ $item['error'] ?? 'No songs yet.' }}</span>
        @endif
      </div>
    </li>
  @endforeach
</ul>
