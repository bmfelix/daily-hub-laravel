@isset($error)
  <p>{{ $error }}</p>
@else
  @if ($concerts)
    <ul class="card-mini-list">
      @foreach (array_slice($concerts, 0, 4) as $show)
        <li><span class="number">{{ \Illuminate\Support\Carbon::parse($show['date'])->format('M j') }}</span><a href="{{ $show['url'] }}" rel="noopener" target="_blank">{{ $show['name'] }}<span class="meta"> · {{ $show['city'] }}</span></a></li>
      @endforeach
    </ul>
  @else
    <p>No shows listed right now.</p>
  @endif
@endisset
