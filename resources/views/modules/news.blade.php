@extends('layouts.hub')

@section('title', $module->title)

@section('content')
  <h1>{{ $module->title }}</h1>

  @isset($error)
    <p class="notice">{{ $error }}</p>
  @else
    <ol class="list">
      @foreach ($stories as $story)
        <li class="panel">
          <a href="{{ $story['url'] }}" rel="noopener" target="_blank">{{ $story['title'] }}</a>
          <span class="meta">{{ $story['points'] }} points · by {{ $story['author'] }} · <a href="{{ $story['discussion'] }}" rel="noopener" target="_blank">{{ $story['comments'] }} comments</a></span>
        </li>
      @endforeach
    </ol>
  @endisset

  <p class="source">Data: Hacker News</p>
@endsection
