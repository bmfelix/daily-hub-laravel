@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <h1>{{ $module->title }}</h1>

  <div class="stations">
    @foreach ($stations as $item)
      <section class="panel">
        <h2>{{ $item['station']->name }} <span class="meta">{{ $item['station']->frequency }}</span></h2>
        @isset($item['error'])
          <p class="notice">{{ $item['error'] }}</p>
        @else
          <ol class="tracks">
            @forelse ($item['tracks'] as $track)
              <li class="now-playing @if ($track['playing']) is-playing @endif">
                @if ($track['image'])<img src="{{ $track['image'] }}" alt="" width="48" height="48">@endif
                <div>
                  <span class="meta">{{ $track['playing'] ? 'Now playing' : \Illuminate\Support\Carbon::createFromTimestamp($track['start'], 'America/New_York')->format('g:i A') }}</span>
                  <strong>{{ $track['title'] }}</strong>
                  <a class="artist-link" href="{{ route('artists.lookup', ['name' => $track['artist']]) }}">{{ $track['artist'] }}</a>
                </div>
              </li>
            @empty
              <li class="lead">No songs yet.</li>
            @endforelse
          </ol>
        @endisset
      </section>
    @endforeach
  </div>

  <p class="source">Data: iHeartRadio (WEBN) and Triton Digital (WKRQ). Times are Cincinnati time.</p>
@endsection
