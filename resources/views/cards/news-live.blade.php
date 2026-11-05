@isset($error)
  <p>{{ $error }}</p>
@else
  <ol class="card-mini-list">
    @foreach (array_slice($stories, 0, 3) as $story)
      <li><span class="number" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><a href="{{ $story['url'] }}" rel="noopener" target="_blank" style="color: inherit; text-decoration: none">{{ $story['title'] }}</a></li>
    @endforeach
  </ol>
@endisset
