@extends('layouts.hub')

@section('title', $module->title)

@section('content')
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Source+Serif+4:opsz,wght@8..60,400;8..60,600&family=UnifrakturMaguntia&display=swap">
<link rel="stylesheet" href="{{ asset('css/news.css') }}">
<div class="theme-news">
  <article class="paper">
    <header class="paper-masthead">
      <p class="paper-ears"><span>Cincinnati edition</span><span>Top stories</span></p>
      <h1 class="paper-title">The Daily Hub</h1>
      <p class="paper-dateline"><span>{{ now()->format('l, F j, Y') }}</span><span>{{ $module->title }}</span><span>Free</span></p>
    </header>

    @isset($error)
      <p class="notice">{{ $error }}</p>
    @else
      <ol class="paper-stories">
        @foreach ($stories as $story)
          <li class="paper-story @if ($loop->first) paper-lead @endif">
            <a class="paper-headline" href="{{ $story['url'] }}" rel="noopener" target="_blank">{{ $story['title'] }}</a>
            <span class="paper-byline">{{ $story['source'] }}@if ($story['published'])@if ($story['source']) · @endif<time datetime="{{ $story['published']->toIso8601String() }}">{{ $story['published']->format('M j, g:i A') }}</time>@endif</span>
          </li>
        @endforeach
      </ol>
    @endisset

    <p class="source">Data: Google News</p>
  </article>
</div>
@endsection
