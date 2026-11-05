@if ($songs)
  <ol class="card-mini-list">
    @foreach ($songs as $row)
      <li><span class="number">{{ sprintf('%02d', $loop->iteration) }}</span><span>{{ $row['title'] }}<span class="meta"> · {{ $row['artist'] }} · {{ $row['plays'] }} {{ \Illuminate\Support\Str::plural('play', $row['plays']) }}</span></span></li>
    @endforeach
  </ol>
@else
  <p>{{ $module->summary }}</p>
@endif
