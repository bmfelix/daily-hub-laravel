@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <p class="kicker">Tour dates</p>
  <h1>{{ $module->title }}</h1>

  @if ($failed ?? 0)<p class="notice">{{ $failed }} {{ \Illuminate\Support\Str::plural('calendar', $failed) }} could not be read.</p>@endif
  @isset($error)
    <p class="notice">{{ $error }}</p>
  @else
    @forelse ($days as $day)
      <section class="agenda-day">
        <h2>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('l, F j') }}</h2>
        <ul class="list">
          @foreach ($day['items'] as $item)
            <li class="panel agenda-item">
              <span class="agenda-time">{{ $item['all_day'] ? 'All day' : \Illuminate\Support\Carbon::parse($item['time'])->format('g:i A') }}</span>
              <strong>{{ $item['title'] }}</strong>
              @if ($item['location'])<span class="meta">{{ $item['location'] }}</span>@endif
            </li>
          @endforeach
        </ul>
      </section>
    @empty
      <p class="lead">Nothing on your calendars for two weeks.</p>
    @endforelse
  @endisset
@endsection
