@if ($track)
  <div class="now-playing">
    <span class="art">@if ($track['artwork'])<img src="{{ $track['artwork'] }}" alt="" width="230" height="230">@endif</span>
    <div>
      <span class="on-air @if ($track['playing']) is-live @endif">{{ $track['playing'] ? 'Playing now' : 'Last played '.\Illuminate\Support\Carbon::parse($track['played_at'])->diffForHumans() }}</span>
      <strong>{{ $track['title'] }}</strong>
      <a class="artist-link" href="{{ route('artists.lookup', ['name' => $track['first_artist']]) }}">{{ $track['artist'] }}</a>
      @if ($track['album'])<span class="meta">{{ $track['album'] }}</span>@endif
      @if ($track['url'])<a class="spotify-open" href="{{ $track['url'] }}" rel="noopener" target="_blank">Open in Spotify</a>@endif
      <a class="spotify-open" href="{{ route('modules.listening') }}">Listening stats</a>
    </div>
  </div>
@elseif (isset($error))
  <p>{{ $error }}</p>
@elseif (! $configured)
  <p>Spotify listening is not set up yet.</p>
@else
  <p>Nothing to show yet.</p>
@endif
