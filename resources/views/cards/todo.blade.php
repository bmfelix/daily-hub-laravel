@if ($openTasks->isNotEmpty())
  <ul class="card-mini-list">
    @foreach ($openTasks as $task)<li><span class="tick" aria-hidden="true">☐</span><span>{{ $task->title }}</span></li>@endforeach
  </ul>
  @if ($openCount > $openTasks->count())<p class="meta">{{ $openCount }} open in all</p>@endif
@else
  <p>Nothing left to do.</p>
@endif
