@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <p class="kicker">Liner notes</p>
  <h1>{{ $module->title }}</h1>

  <div class="split">
    <aside>
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
      @if ($note)
        <h2>{{ $note->title }}</h2>
        <div class="note-body">{!! nl2br(e($note->body)) !!}</div>
      @else
        <p class="lead">Choose a note to read it.</p>
      @endif
    </section>
  </div>
@endsection
