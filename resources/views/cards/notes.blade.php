@if ($latest)
  <strong>{{ $latest->title }}</strong>
  <p>{{ \Illuminate\Support\Str::limit((string) $latest->body, 140) }}</p>
  <p class="meta">Updated {{ $latest->updated_at->format('M j') }}</p>
@else
  <p>No notes yet.</p>
@endif
