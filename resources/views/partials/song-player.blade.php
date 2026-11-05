@if ($song->hasPreview())
  @include('partials.preview-button', ['song' => $song])
@endif
<span class="song-links meta">
  @if ($song->apple_url)<a href="{{ $song->apple_url }}" rel="noopener" target="_blank">Apple Music</a>@endif
  @if ($song->deezer_url)<a href="{{ $song->deezer_url }}" rel="noopener" target="_blank">Deezer</a>@endif
  <a href="{{ $song->spotifyUrl() }}" rel="noopener" target="_blank">Spotify</a>
  <a href="{{ $song->youtubeMusicUrl() }}" rel="noopener" target="_blank">YouTube Music</a>
</span>
