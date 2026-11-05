@isset($error)
  <p>{{ $error }}</p>
@else
  <p class="readout">{{ round($current['temperature']) }}{{ $temperature_unit }}</p>
  <p>{{ $current['label'] }} in {{ $place['name'] }}</p>
  <p class="meta">Feels like {{ round($current['feels_like']) }}° · Wind {{ round($current['wind']) }} {{ $wind_unit }}</p>
  <ul class="mini-week">
    @foreach (array_slice($days, 1, 4) as $day)
      <li><strong>{{ $day['weekday'] }}</strong><span>{{ $day['label'] }}</span><span>{{ round($day['low']) }}° / {{ round($day['high']) }}°</span></li>
    @endforeach
  </ul>
@endisset
