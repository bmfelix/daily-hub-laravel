@extends('layouts.hub')

@section('title', $module->title)

@section('content')
<div class="theme-radio">
  @include('partials.airwaves')
  <p class="kicker">The weekly countdown</p>
  <h1>{{ $module->title }}</h1>

  <nav class="week-nav" aria-label="Week">
    @if ($previousWeek)<a class="pill" href="{{ $module->url(['week' => $previousWeek->toDateString()]) }}">← Earlier</a>@endif
    <strong>{{ $monday->format('M j') }} – {{ $sunday->format('M j') }}</strong>
    @if ($nextWeek)<a class="pill" href="{{ $module->url(['week' => $nextWeek->toDateString()]) }}">Later →</a>@endif
    <span class="meta">{{ $total }} {{ \Illuminate\Support\Str::plural('play', $total) }} counted</span>
  </nav>

  @forelse ($stations as $station)
    <section class="chart-station">
      <h2>{{ $station['name'] }}</h2>
      <div class="chart-columns">
        <div>
          <h3>Top songs</h3>
          <ol class="chart">
            @foreach ($station['songs'] as $row)
              <li>
                <span class="chart-rank">{{ $loop->iteration }}</span>
                <span class="chart-row">
                  <strong>{{ $row['title'] }}</strong>
                  <a class="artist-link" href="{{ route('artists.lookup', ['name' => $row['artist']]) }}">{{ $row['artist'] }}</a>
                  <span class="chart-bar" aria-hidden="true"><span style="width: {{ $row['bar'] }}%"></span></span>
                </span>
                <span class="meta">{{ $row['plays'] }}</span>
                @if ($row['song']?->hasPreview())@include('partials.preview-button', ['song' => $row['song']])@endif
              </li>
            @endforeach
          </ol>
        </div>
        <div>
          <h3>Top artists</h3>
          <ol class="chart">
            @foreach ($station['artists'] as $row)
              <li>
                <span class="chart-rank">{{ $loop->iteration }}</span>
                <span class="chart-row">
                  <a class="artist-link" href="{{ route('artists.lookup', ['name' => $row['artist']]) }}"><strong>{{ $row['artist'] }}</strong></a>
                  <span class="chart-bar" aria-hidden="true"><span style="width: {{ $row['bar'] }}%"></span></span>
                </span>
                <span class="meta">{{ $row['plays'] }}</span>
              </li>
            @endforeach
          </ol>
        </div>
      </div>
    </section>
  @empty
    <p class="notice">No plays saved for this week yet. Songs are counted as the listener hears them.</p>
  @endforelse

  <p class="source">Counted by Daily Hub from each station's song feed, from the day tracking began.</p>
</div>
@endsection
