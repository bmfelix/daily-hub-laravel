@extends('layouts.hub')

@section('title', $module->title)

@section('content')
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500..700;1,500..700&family=EB+Garamond:ital,wght@0,400..600;1,400..600&display=swap">
<link rel="stylesheet" href="{{ asset('css/score.css') }}">
<div class="theme-score">
  <p class="kicker">Composed by</p>
  <h1>{{ $profile['name'] ?: $module->title }}</h1>

  @if ($profile['headline'])<p class="about-headline">{{ $profile['headline'] }}</p>@endif
  @if ($profile['bio'])
    <div class="artist-about">{!! nl2br(e($profile['bio'])) !!}</div>
  @endif

  <ul class="artist-actions">
    @if ($profile['website'])<li><a class="pill pill-solid" href="{{ $profile['website'] }}" rel="noopener" target="_blank">Website</a></li>@endif
    @if ($githubUsername)<li><a class="pill" href="https://github.com/{{ $githubUsername }}" rel="noopener" target="_blank">GitHub</a></li>@endif
    @if ($profile['email'])<li><a class="pill" href="mailto:{{ $profile['email'] }}">Email</a></li>@endif
  </ul>

  @if ($profile['projects'])
    <section class="artist-section about-section">
      <h2>Projects</h2>
      <ul class="project-list">
        @foreach ($profile['projects'] as $project)
          <li class="panel">
            <h3>{{ $project['title'] }}</h3>
            @if ($project['description'])<p>{{ $project['description'] }}</p>@endif
            @if ($project['tech'])<ul class="chips chips-quiet">@foreach ($project['tech'] as $item)<li>{{ $item }}</li>@endforeach</ul>@endif
            <p class="project-links">
              @if ($project['url'])<a href="{{ $project['url'] }}" rel="noopener" target="_blank">Live</a>@endif
              @if ($project['repo_url'])<a href="{{ $project['repo_url'] }}" rel="noopener" target="_blank">Code</a>@endif
            </p>
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  @if ($repositories)
    <section class="artist-section about-section">
      <h2>Recently on GitHub</h2>
      <ul class="project-list">
        @foreach ($repositories as $repo)
          <li class="panel">
            <h3><a href="{{ $repo['url'] }}" rel="noopener" target="_blank">{{ $repo['name'] }}</a></h3>
            @if ($repo['description'])<p>{{ $repo['description'] }}</p>@endif
            @if ($repo['language'])<span class="meta">{{ $repo['language'] }}</span>@endif
          </li>
        @endforeach
      </ul>
    </section>
  @endif
</div>
@endsection
