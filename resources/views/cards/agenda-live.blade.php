@isset($error)
  <p>{{ $error }}</p>
@else
  @if ($items)
    <ul class="card-mini-list">
      @foreach (array_slice($items, 0, 4) as $item)
        <li><span class="number">{{ \Illuminate\Support\Carbon::parse($item['day'])->format('M j') }}</span><span>{{ $item['title'] }}<span class="meta"> · {{ $item['all_day'] ? 'All day' : \Illuminate\Support\Carbon::parse($item['time'])->format('g:i A') }}</span></span></li>
      @endforeach
    </ul>
  @elseif ($failed)
    <p>Your calendars could not be read.</p>
  @else
    <p>Nothing on your calendars for two weeks.</p>
  @endif
@endisset
