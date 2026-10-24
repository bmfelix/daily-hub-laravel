<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'Daily Hub') · Daily Hub</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
  <header class="site-header">
    <div class="wrap header-inner">
      <a class="brand" href="{{ route('home') }}">Daily Hub</a>
      <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu">Menu</button>
      <nav id="site-menu" class="site-menu" aria-label="Main">
        <ul>
          <li><a href="{{ route('home') }}"@if (request()->routeIs('home')) aria-current="page"@endif>Home</a></li>
          @foreach ($menuModules as $item)
            <li><a href="{{ $item->url() }}"@if (request()->routeIs("modules.{$item->slug}")) aria-current="page"@endif>{{ $item->title }}</a></li>
          @endforeach
        </ul>
      </nav>
    </div>
  </header>

  <main class="wrap">
    @yield('content')
  </main>

  <footer class="site-footer wrap">Daily Hub · Laravel version</footer>

  <div class="loading-overlay" id="loading-overlay" role="status" aria-live="polite" hidden>
    <div class="loading-box">
      <span class="spinner" aria-hidden="true"></span>
      <strong>Loading artist details…</strong>
      <span class="meta">The first visit fetches everything from MusicBrainz, which can take up to 30 seconds for big artists. After that it is instant.</span>
    </div>
  </div>

  <script>
    // Artist pages may need a MusicBrainz lookup first. If the next page is slow
    // to arrive, show a "please wait" overlay (saved artists open before it appears).
    (function () {
      const overlay = document.getElementById("loading-overlay");
      const artistPages = "{{ url('/artists') }}";
      let timer;
      document.addEventListener("click", (event) => {
        const link = event.target.closest("a[href]");
        if (!link || !link.href.startsWith(artistPages) || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        timer = setTimeout(() => { overlay.hidden = false; }, 250);
      });
      // Hide it again when coming back with the Back button.
      window.addEventListener("pageshow", () => { clearTimeout(timer); overlay.hidden = true; });
    })();

    const toggle = document.querySelector(".menu-toggle");
    const menu = document.getElementById("site-menu");
    toggle.addEventListener("click", () => {
      const open = toggle.getAttribute("aria-expanded") === "true";
      toggle.setAttribute("aria-expanded", String(!open));
      menu.classList.toggle("is-open", !open);
    });
  </script>
</body>
</html>
