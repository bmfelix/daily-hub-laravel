@extends('layouts.hub')

@section('title', 'Home')

@section('content')
  <header class="home-intro">
    <p class="kicker">{{ now()->format('l, F j') }}</p>
    <h1>Your day, <em>on repeat.</em></h1>
  </header>

  <div class="board">
    <div class="card card-spotify">
      <h2>Spotify</h2>
      <div class="spotify-layout @unless ($playlists) is-single @endunless">
        <div class="spotify-listening" data-card-url="{{ route('spotify.card') }}" data-card-every="30">
          <p>Checking Spotify…</p>
        </div>
        @if ($playlists)
          <div class="spotify-player">
            @if (count($playlists) > 1)
              <div class="playlist-buttons" role="group" aria-label="Choose a playlist">
                @foreach ($playlists as $playlist)
                  <button class="pill @if ($loop->first) is-chosen @endif" type="button" aria-pressed="{{ $loop->first ? 'true' : 'false' }}" aria-label="Playlist {{ $loop->iteration }}" data-playlist="{{ $playlist }}">{{ $loop->iteration }}</button>
                @endforeach
              </div>
            @endif
            <iframe class="spotify-playlist" title="Spotify playlist" src="{{ $playlists[0] }}" height="352" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"></iframe>
          </div>
        @endif
      </div>
    </div>

    @foreach ($cards as $card)
      @if ($card['module']->slug === 'radio')
        @include($card['view'], ['module' => $card['module'], ...$card['data']])
      @else
        <div class="card card-{{ $card['module']->slug }}">
          <h2><a class="card-link" href="{{ $card['module']->url() }}">{{ $card['module']->title }}</a></h2>
          @include($card['view'], ['module' => $card['module'], ...$card['data']])
        </div>
      @endif
    @endforeach
  </div>

  <script src="{{ asset('js/cards.js') }}" defer></script>

  {{-- Live Radio cards: Reverb pushes new cards over a WebSocket when a song changes. --}}
  <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.6.0/dist/web/pusher.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/laravel-echo@2.6.1/dist/echo.iife.js"></script>
  <script>
    (function () {
      if (!document.querySelector('[data-live-card="radio"]')) return;

      const reverb = @json(['key' => config('reverb.apps.apps.0.key'), ...config('hub.reverb_public')]);
      const EchoClass = window.Echo.default ?? window.Echo;
      const echo = new EchoClass({
        broadcaster: 'reverb',
        key: reverb.key,
        // "localhost" means Reverb shares the page's address, whichever one the site is opened at.
        wsHost: reverb.host === 'localhost' ? window.location.hostname : reverb.host,
        wsPort: reverb.port,
        wssPort: reverb.port,
        forceTLS: window.location.protocol === 'https:',
        enabledTransports: ['ws', 'wss'],
      });

      // The leading dot means "use this exact event name" (set by SongChanged::broadcastAs).
      echo.channel('radio').listen('.song.changed', (event) => {
        const card = document.querySelector('[data-live-card="radio"]');
        if (card) card.outerHTML = event.html;
        document.dispatchEvent(new Event('cards-updated')); // the player re-marks the new buttons
      });
    })();
  </script>
@endsection
