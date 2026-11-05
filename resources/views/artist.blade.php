@extends('layouts.hub')

@section('title', $artist['name'])

@section('content')
<article class="artist">
  <header class="artist-hero">
    @if ($artist['image'])<img class="artist-backdrop" src="{{ $artist['image'] }}" alt="" aria-hidden="true" onerror="this.remove()">@endif
    <div class="artist-photo">
      @if ($artist['image'])
        <img src="{{ $artist['image'] }}" alt="{{ $artist['name'] }}" width="320" height="320" onerror="this.remove()">
      @endif
      <span class="artist-monogram" aria-hidden="true">{{ mb_substr($artist['name'], 0, 1) }}</span>
    </div>

    <div class="artist-intro">
      <p class="artist-kicker">{{ $artist['type'] ?? 'Artist' }}@if ($artist['gender']) · {{ $artist['gender'] }}@endif</p>
      <h1>{{ $artist['name'] }}</h1>
      @if ($artist['disambiguation'])<p class="artist-note">{{ $artist['disambiguation'] }}</p>@endif

      <dl class="artist-facts">
        @if ($artist['origin'])<div><dt>From</dt><dd>{{ $artist['origin'] }}</dd></div>@endif
        @if ($artist['active'])<div><dt>{{ $artist['ended'] ? 'Active' : 'Since' }}</dt><dd>{{ $artist['active'] }}</dd></div>@endif
        @if ($artist['rating'] !== null)<div><dt>Rating</dt><dd>★ {{ $artist['rating'] }} <span class="meta">({{ $artist['rating_votes'] }} votes)</span></dd></div>@endif
        <div><dt>Releases</dt><dd>{{ $artist['release_count'] }}</dd></div>
      </dl>

      @if ($artist['genres'])
        <ul class="chips" aria-label="Genres">
          @foreach (array_slice($artist['genres'], 0, 8) as $genre)<li>{{ $genre }}</li>@endforeach
        </ul>
      @endif

      @if ($artist['official_url'] || $artist['quick_links'])
        <ul class="artist-actions" aria-label="Listen">
          @if ($artist['official_url'])<li><a class="pill pill-solid" href="{{ $artist['official_url'] }}" rel="noopener" target="_blank">Official site</a></li>@endif
          @foreach ($artist['quick_links'] as $link)
            <li><a class="pill" href="{{ $link['url'] }}" rel="noopener" target="_blank">{{ $link['label'] }}</a></li>
          @endforeach
        </ul>
      @endif
    </div>
  </header>

  @if ($artist['wikipedia'])
    <section class="artist-section">
      <h2>About</h2>
      <div class="artist-about">
        <p>{{ $artist['wikipedia']['lead'] }}</p>
        @if ($artist['wikipedia']['rest'])
          <details>
            <summary>Read more</summary>
            @foreach (explode("\n", $artist['wikipedia']['rest']) as $paragraph)
              @if (trim($paragraph) !== '')<p>{{ $paragraph }}</p>@endif
            @endforeach
          </details>
        @endif
      </div>
      <p class="meta">From <a href="{{ $artist['wikipedia']['url'] }}" rel="noopener" target="_blank">{{ $artist['wikipedia']['title'] }} on Wikipedia</a>, under <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="noopener" target="_blank">CC BY-SA 4.0</a>.</p>
    </section>
  @endif

  @if ($artist['songs']->isNotEmpty())
    <section class="artist-section">
      <h2>Heard on the radio</h2>
      <ul class="songs">
        @foreach ($artist['songs'] as $song)
          <li>
            <span class="art">@if ($song->artwork_url)<img src="{{ $song->artwork_url }}" alt="" width="64" height="64" loading="lazy">@endif</span>
            <div class="track-text">
              <strong>{{ $song->title }}</strong>
              @if ($song->album)<span class="meta">{{ $song->album }}</span>@endif
            </div>
            @include('partials.song-player', ['song' => $song])
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  @foreach (['Members' => $artist['members'], 'Member of' => $artist['member_of']] as $heading => $people)
    @if ($people)
      <section class="artist-section">
        <h2>{{ $heading }}</h2>
        <ul class="people">
          @foreach ($people as $person)
            <li class="person @unless ($person['current']) is-former @endunless">
              <span class="avatar" aria-hidden="true">{{ mb_substr($person['name'], 0, 1) }}</span>
              <div>
                <a href="{{ route('artists.show', $person['mbid']) }}">{{ $person['name'] }}</a>
                <span class="meta">{{ $person['years'] }}@if ($heading === 'Members')@if ($person['original']) · original member @endif @unless ($person['current']) · former @endunless @endif</span>
                @if ($person['roles'])<span class="roles">{{ implode(', ', $person['roles']) }}</span>@endif
              </div>
            </li>
          @endforeach
        </ul>
      </section>
    @endif
  @endforeach

  @if ($artist['discography'])
    <section class="artist-section">
      <h2>Discography</h2>
      @foreach ($artist['discography'] as $section)
        <details class="releases" @if ($loop->first) open @endif>
          <summary>{{ $section['label'] }} <span class="meta">{{ $section['total'] }}</span></summary>
          <ul class="covers">
            @foreach ($section['releases'] as $release)
              <li>
                <a href="https://musicbrainz.org/release-group/{{ $release['mbid'] }}" rel="noopener" target="_blank">
                  <span class="cover-sleeve"><span class="cover">
                    <img src="{{ $release['cover'] }}" data-fallback="{{ $release['apple_artwork'] }}" alt="" width="250" height="250" loading="lazy" onerror="if (this.dataset.fallback) { this.src = this.dataset.fallback; this.dataset.fallback = ''; } else { this.remove(); }">
                    <span class="cover-fallback" aria-hidden="true">♪</span>
                  </span></span>
                  <strong>{{ $release['title'] }}</strong>
                  <span class="meta">{{ $release['year'] ?: 'Year unknown' }}</span>
                </a>
                @if ($release['apple_url'])<a class="meta cover-source" href="{{ $release['apple_url'] }}" rel="noopener" target="_blank">Apple Music</a>@endif
              </li>
            @endforeach
          </ul>
          @if ($section['total'] > count($section['releases']))
            <p class="meta">Showing {{ count($section['releases']) }} of {{ $section['total'] }}. <a href="{{ $artist['musicbrainz_url'] }}" rel="noopener" target="_blank">See them all on MusicBrainz</a></p>
          @endif
        </details>
      @endforeach
    </section>
  @endif

  @if ($artist['links'])
    <section class="artist-section">
      <h2>Links</h2>
      <div class="link-groups">
        @foreach ($artist['links'] as $group)
          <div>
            <h3>{{ $group['label'] }}</h3>
            <ul class="pills">
              @foreach ($group['items'] as $link)
                <li><a class="pill" href="{{ $link['url'] }}" rel="noopener" target="_blank" title="{{ $link['kind'] }}">{{ $link['label'] }}</a></li>
              @endforeach
            </ul>
          </div>
        @endforeach
      </div>
    </section>
  @endif

  @foreach ($artist['related'] as $kind => $people)
    <section class="artist-section">
      <h2>{{ $kind }}</h2>
      <ul class="pills">
        @foreach ($people as $person)
          <li><a class="pill" href="{{ route('artists.show', $person['mbid']) }}">{{ $person['name'] }}@if ($person['years']) <span class="meta">{{ $person['years'] }}</span>@endif</a></li>
        @endforeach
      </ul>
    </section>
  @endforeach

  <section class="artist-section artist-details">
    @if ($artist['aliases'])
      <div><h2>Also known as</h2><p>{{ implode(' · ', $artist['aliases']) }}</p></div>
    @endif
    @if ($artist['tags'])
      <div><h2>Tags</h2><ul class="chips chips-quiet">@foreach ($artist['tags'] as $tag)<li>{{ $tag }}</li>@endforeach</ul></div>
    @endif
    <div>
      <h2>Identifiers</h2>
      <dl class="artist-ids">
        <div><dt>MusicBrainz</dt><dd><a href="{{ $artist['musicbrainz_url'] }}" rel="noopener" target="_blank">{{ $artist['mbid'] }}</a></dd></div>
        @foreach ($artist['isnis'] as $isni)<div><dt>ISNI</dt><dd>{{ $isni }}</dd></div>@endforeach
        @foreach ($artist['ipis'] as $ipi)<div><dt>IPI</dt><dd>{{ $ipi }}</dd></div>@endforeach
      </dl>
    </div>
  </section>

  <p class="source">Data from MusicBrainz, saved {{ $artist['fetched_at']->format('j M Y') }}. Photo from Wikimedia Commons, covers from the Cover Art Archive and Apple Music.</p>
</article>
@endsection
