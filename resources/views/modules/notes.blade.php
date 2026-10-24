@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <h1>{{ $module->title }}</h1>

  <div class="split">
    <aside>
      <p><a href="{{ $module->url() }}">+ New note</a></p>
      <ul class="list">
        @forelse ($notes as $item)
          <li class="panel @if ($note?->is($item)) is-active @endif">
            <a href="{{ $module->url(['note' => $item->id]) }}">{{ $item->title }}</a>
            <span class="meta">Updated {{ $item->updated_at->format('j M Y, H:i') }}</span>
          </li>
        @empty
          <li class="lead">No notes yet.</li>
        @endforelse
      </ul>
    </aside>

    <section class="panel">
      <h2>{{ $note ? 'Edit note' : 'New note' }}</h2>
      <form class="stack-form" method="post">
        @csrf
        @if ($note)<input type="hidden" name="note" value="{{ $note->id }}">@endif
        <div>
          <label for="note-title">Title</label>
          <input id="note-title" name="title" value="{{ old('title', $note?->title) }}" maxlength="200" required>
          @error('title')<ul class="errorlist"><li>{{ $message }}</li></ul>@enderror
        </div>
        <div>
          <label for="note-body">Body</label>
          <textarea id="note-body" name="body" rows="8">{{ old('body', $note?->body) }}</textarea>
        </div>
        <div class="actions">
          <button type="submit" name="action" value="save">Save</button>
          @if ($note)<button class="link-button" type="submit" name="action" value="delete" formnovalidate>Delete</button>@endif
        </div>
      </form>
    </section>
  </div>
@endsection
