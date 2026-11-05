@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <p class="kicker">Live and loud</p>
  <h1>{{ $module->title }}</h1>

  <nav class="filter-pills" aria-label="City">
    <a class="pill @if ($chosen === '') is-chosen @endif" href="{{ $module->url() }}">All cities</a>
    @foreach ($cities as $city)
      <a class="pill @if ($chosen === $city) is-chosen @endif" href="{{ $module->url(['city' => $city]) }}">{{ $city }}</a>
    @endforeach
  </nav>

  @isset($error)
    <p class="notice">{{ $error }}</p>
  @else
    @if ($concerts)
      <ul class="shows">
        @foreach ($concerts as $show)
          @php($date = \Illuminate\Support\Carbon::parse($show['date']))
          <li class="show">
            <a href="{{ $show['url'] }}" rel="noopener" target="_blank">
              <span class="show-image">@if ($show['image'])<img src="{{ $show['image'] }}" alt="" loading="lazy">@endif<time datetime="{{ $show['date'] }}"><b>{{ $date->format('j') }}</b>{{ $date->format('M') }}</time></span>
              <strong>{{ $show['name'] }}</strong>
              <span class="meta">{{ $show['venue'] }} · {{ $show['city'] }}</span>
            </a>
          </li>
        @endforeach
      </ul>
    @else
      <p class="lead">No shows listed right now.</p>
    @endif
  @endisset

  <p class="source">Data: Ticketmaster</p>
@endsection
