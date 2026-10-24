@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <h1>{{ $module->title }}</h1>

  <form class="inline-form" method="post">
    @csrf
    <input type="hidden" name="action" value="add">
    <label for="title">New task</label>
    <input id="title" name="title" value="{{ old('title') }}" maxlength="200" required>
    <button type="submit">Add</button>
  </form>
  @error('title')<p class="notice">{{ $message }}</p>@enderror

  @if ($tasks->isNotEmpty())
    <ul class="list">
      @foreach ($tasks as $task)
        <li class="panel task @if ($task->done) is-done @endif">
          <form method="post">
            @csrf
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="task" value="{{ $task->id }}">
            <button class="link-button" type="submit" aria-label="Mark {{ $task->title }} as {{ $task->done ? 'not done' : 'done' }}">{{ $task->done ? '☑' : '☐' }}</button>
          </form>
          <span class="task-title">{{ $task->title }}</span>
          <form method="post">
            @csrf
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="task" value="{{ $task->id }}">
            <button class="link-button" type="submit">Delete</button>
          </form>
        </li>
      @endforeach
    </ul>
  @else
    <p class="lead">No tasks yet.</p>
  @endif
@endsection
