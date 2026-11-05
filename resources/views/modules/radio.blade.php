@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <p class="kicker">Now spinning in Cincinnati</p>
  <h1>{{ $module->title }}</h1>

  <div class="stations">
    @foreach ($stations as $item)
      <section class="panel station">
        <header class="station-head">
          <h2>{{ $item['station']->name }}</h2>
          <span class="station-tune">
            <span class="meta">{{ $item['station']->frequency }}</span>
            @include('partials.live-button', ['station' => $item['station']])
          </span>
        </header>
        @isset($item['error'])
          <p class="notice">{{ $item['error'] }}</p>
        @else
          <ol class="tracks">
            @forelse ($item['tracks'] as $track)
              @php($art = $track['image'] ?: ($track['song']->artwork_url ?? ''))
              <li class="track @if ($track['playing']) is-playing @endif">
                <span class="art">@if ($art)<img src="{{ $art }}" alt="" width="112" height="112" loading="lazy">@endif</span>
                <div class="track-text">
                  <span class="on-air @if ($track['playing']) is-live @endif">{{ $track['playing'] ? 'On air' : \Illuminate\Support\Carbon::createFromTimestamp($track['start'], 'America/New_York')->format('g:i A') }}</span>
                  <strong>{{ $track['title'] }}</strong>
                  <a class="artist-link" href="{{ route('artists.lookup', ['name' => $track['artist']]) }}">{{ $track['artist'] }}</a>
                </div>
                @if ($track['song'])
                  @include('partials.song-player', ['song' => $track['song']])
                @endif
              </li>
            @empty
              <li class="track lead">No songs yet.</li>
            @endforelse
          </ol>
        @endisset
      </section>
    @endforeach
  </div>

  <p class="source">Data: iHeartRadio (WEBN) and Triton Digital (WKRQ). Times are Cincinnati time. Previews from Apple Music and Deezer. Live audio comes straight from each station and includes its ads.</p>
@endsection
