@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <p class="kicker">Outside the venue</p>
  <h1>{{ $module->title }}</h1>

  <form class="inline-form" method="get">
    <label for="city">City</label>
    <input id="city" name="city" value="{{ $city }}" required>
    <button type="submit">Show weather</button>
  </form>

  @isset($error)
    <p class="notice">{{ $error }}</p>
  @else
    <section class="sky">
      <h2>{{ $place['name'] }}@isset($place['country']), {{ $place['country'] }}@endisset</h2>
      <p class="big">{{ $current['temperature'] }}{{ $temperature_unit }}</p>
      <p>{{ $current['label'] }} · Feels like {{ $current['feels_like'] }}{{ $temperature_unit }} · Wind {{ $current['wind'] }} {{ $wind_unit }}</p>
    </section>

    <h2>Next 5 days</h2>
    <ul class="week">
      @foreach ($days as $day)
        <li>
          <strong>{{ $day['weekday'] }}</strong>
          <span>{{ $day['label'] }}</span>
          <span class="range" aria-hidden="true"><span style="left: {{ $day['bar_start'] }}%; width: {{ $day['bar_width'] }}%"></span></span>
          <span class="temps">{{ $day['low'] }}° / {{ $day['high'] }}°</span>
        </li>
      @endforeach
    </ul>
  @endisset

  <p class="source">Data: Open-Meteo · Place names: OpenStreetMap</p>

  @unless (request()->filled('city') || request()->filled('lat'))
    <script>
      // With no city chosen, ask the browser where the visitor is. Coordinates are
      // rounded to about 1 km. If they say no, the page stays on the default city.
      navigator.geolocation?.getCurrentPosition((position) => {
        const url = new URL(window.location.href);
        url.searchParams.set("lat", position.coords.latitude.toFixed(2));
        url.searchParams.set("lon", position.coords.longitude.toFixed(2));
        window.location.replace(url);
      }, () => {}, { maximumAge: 600000, timeout: 10000 });
    </script>
  @endunless
@endsection
