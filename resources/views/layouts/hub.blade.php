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

  <script>
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
