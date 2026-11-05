@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <p class="kicker">The set list</p>
  <h1>{{ $module->title }}</h1>

  @if ($tasks->isNotEmpty())
    <ul class="list">
      @foreach ($tasks as $task)
        <li class="panel task @if ($task->done) is-done @endif">
          <span aria-label="{{ $task->done ? 'Done' : 'Not done' }}">{{ $task->done ? '☑' : '☐' }}</span>
          <span class="task-title">{{ $task->title }}</span>
        </li>
      @endforeach
    </ul>
  @else
    <p class="lead">No tasks yet.</p>
  @endif
@endsection
