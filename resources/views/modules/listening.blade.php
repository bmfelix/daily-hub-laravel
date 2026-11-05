@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <p class="kicker">On heavy rotation</p>
  <h1>{{ $module->title }}</h1>

  <nav class="filter-pills" aria-label="Time period">
    @foreach ($ranges as $value => $label)
      <a class="pill @if ($value === $chosen) is-chosen @endif" href="{{ $module->url(['range' => $value]) }}">{{ $label }}</a>
    @endforeach
  </nav>

  @isset($error)
    <p class="notice">{{ $error }}</p>
  @else
    <section class="artist-section">
      <h2>Top artists</h2>
      <ol class="top-artists">
        @foreach ($artists as $artist)
          <li>
            <a href="{{ $artist['url'] }}" rel="noopener" target="_blank">
              <span class="art">@if ($artist['image'])<img src="{{ $artist['image'] }}" alt="" loading="lazy">@endif</span>
              <strong>{{ $loop->iteration }}. {{ $artist['name'] }}</strong>
              @if ($artist['genres'])<span class="meta">{{ implode(' · ', $artist['genres']) }}</span>@endif
            </a>
          </li>
        @endforeach
      </ol>
    </section>

    <section class="artist-section">
      <h2>Top tracks</h2>
      <ol class="songs">
        @foreach ($tracks as $track)
          <li>
            <span class="art">@if ($track['artwork'])<img src="{{ $track['artwork'] }}" alt="" loading="lazy">@endif</span>
            <div class="track-text">
              <strong>{{ $loop->iteration }}. {{ $track['title'] }}</strong>
              <a class="artist-link" href="{{ $track['url'] }}" rel="noopener" target="_blank">{{ $track['artist'] }}</a>
            </div>
          </li>
        @endforeach
      </ol>
    </section>
  @endisset

  <p class="source">Data: Spotify</p>
@endsection
