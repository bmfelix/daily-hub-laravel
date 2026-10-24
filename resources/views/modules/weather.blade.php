@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <h1>{{ $module->title }}</h1>

  <form class="inline-form" method="get">
    <label for="city">City</label>
    <input id="city" name="city" value="{{ $city }}" required>
    <button type="submit">Show weather</button>
  </form>

  @isset($error)
    <p class="notice">{{ $error }}</p>
  @else
    <section class="panel">
      <h2>{{ $place['name'] }}@isset($place['country']), {{ $place['country'] }}@endisset</h2>
      <p class="big">{{ $current['temperature'] }}°C</p>
      <p>{{ $current['label'] }} · Feels like {{ $current['feels_like'] }}°C · Wind {{ $current['wind'] }} km/h</p>
    </section>

    <h2>Next 5 days</h2>
    <ul class="forecast">
      @foreach ($days as $day)
        <li class="panel">
          <strong>{{ $day['date'] }}</strong>
          <span>{{ $day['label'] }}</span>
          <span>{{ $day['low'] }}° / {{ $day['high'] }}°</span>
        </li>
      @endforeach
    </ul>
  @endisset

  <p class="source">Data: Open-Meteo</p>
@endsection
