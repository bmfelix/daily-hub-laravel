@extends('layouts.hub')

@section('title', 'Home')

@section('content')
  <h1>Your day at a glance</h1>
  <p class="lead">Pick a module from the menu or below.</p>

  <div class="cards">
    @foreach ($cards as $card)
      <div class="card">
        <h2><a class="card-link" href="{{ $card['module']->url() }}">{{ $card['module']->title }}</a></h2>
        @include($card['view'], ['module' => $card['module'], ...$card['data']])
      </div>
    @endforeach
  </div>

  {{-- Live Radio card: Reverb pushes a new card over a WebSocket when a song changes. --}}
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
        wsHost: reverb.host,
        wsPort: reverb.port,
        wssPort: reverb.port,
        forceTLS: reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
      });

      // The leading dot means "use this exact event name" (set by SongChanged::broadcastAs).
      echo.channel('radio').listen('.song.changed', (event) => {
        const card = document.querySelector('[data-live-card="radio"]');
        if (card) card.outerHTML = event.html;
      });
    })();
  </script>
@endsection
