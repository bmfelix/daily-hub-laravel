# Daily Hub · Laravel

A personal dashboard. Each menu link is one module, and each module is one class that calls its own API or database table.

This is one of three builds of the same app (Django, Next.js, Laravel), so the frameworks can be compared side by side.

## Modules

| Page     | Class            | Data source                                   |
|----------|------------------|-----------------------------------------------|
| Weather  | `WeatherModule`  | [Open-Meteo](https://open-meteo.com) (no key) |
| News     | `NewsModule`     | [Hacker News search API](https://hn.algolia.com/api) (no key) |
| To-do    | `TodoModule`     | Postgres (`Task` model)                       |
| Concerts | `ConcertsModule` | [Ticketmaster Discovery API](https://developer.ticketmaster.com) (free key) |
| Notes    | `NotesModule`    | Postgres (`Note` model)                       |
| Radio    | `RadioModule`    | [iHeartRadio](https://www.iheart.com) song history (WEBN) and [Triton Digital](https://www.tritondigital.com) now-playing feed (WKRQ), no keys |
| Charts   | `ChartsModule`   | Postgres (`Play` model) |
| Agenda   | `AgendaModule`   | Google or Apple calendar links |
| Listening | `ListeningModule` | [Spotify Web API](https://developer.spotify.com/documentation/web-api) (your own app) |
| About    | `AboutModule`    | `config/hub.php`, [GitHub API](https://docs.github.com/rest) (no key) |
| How it's built | `BuiltModule` | Written by hand, linked from the footer |

Modules can also give the homepage a live card instead of their summary. Each station has its own card showing the song on air, with **Listen live** and a song preview. WEBN's newest song can appear about a minute after it starts, so its card may briefly say "Last played".

## How it is built

- `app/Modules/HubModule.php` defines the abstract `HubModule` class. Each module returns its page data from `load()`. Pages are read-only, so the only routes are GET.
- API modules get a client class (for example `App\Services\OpenMeteoClient`) through constructor injection. The clients use Laravel's `Http` client, so tests replace every call with `Http::fake()` and run without the internet.
- `config/hub.php` holds the module list. `App\Modules\ModuleRegistry` builds the modules from it, and the routes, the menu, and the homepage cards all come from the registry. To add a page, write a module class and a Blade view in `resources/views/modules/`, then add the class to `config/hub.php`.
- The menu collapses into a "Menu" button below 1040px wide.
- No Vite build is needed: the stylesheet is plain CSS in `public/css/style.css`, and the audio player and homepage cards are plain JavaScript in `public/js/`.
- Cards that need an outside service load after the page opens, so a slow service never holds it up. A module opts in with `$liveCard = true`, and `?card=1` returns just its card.

## Live updates

The station cards on the homepage update without a reload, using **Laravel Reverb** (Laravel's WebSocket server):

1. The **listener** container (`php artisan radio:listen`, `App\Services\Radio\RadioListener`) asks both stations for their song every 15 seconds. The feeds cannot be subscribed to, so it polls.
2. When a song changes (or the current song ends), it dispatches `App\Events\SongChanged`, a broadcast event carrying the re-rendered cards, on the public `radio` channel.
3. The **reverb** container (`php artisan reverb:start`) pushes the event to every connected browser.
4. The homepage loads **Laravel Echo** and Pusher JS from a CDN, listens for `.song.changed`, and swaps in the new cards.

Two Reverb addresses are configured: `REVERB_HOST`/`REVERB_PORT` is how the app reaches Reverb inside Docker (`reverb:8080`), and `REVERB_PUBLIC_PORT` is the port browsers use, on the same address as the page. `REVERB_ALLOWED_ORIGINS` limits which sites may connect.

Note: Reverb 1.12 needs Guzzle 7, so Guzzle is held at 7 until a Reverb release supports Guzzle 8.

## Artist pages

Click an artist on the homepage card or the Radio page to see everything MusicBrainz has on them: photo, origin, active years, rating, genres, members (with roles and years), the full discography with cover art, links (listen, official, social, concerts, buy, reference), aliases, related artists, and identifiers.

- **Fetched once, then saved** in Postgres: `artists` (the full MusicBrainz record and every release) and `artist_names` (which radio name matched which artist, including names with no match). Saved artists are refreshed after 30 days; unmatched names are retried after 7. See `App\Services\MusicBrainz\ArtistLibrary`.
- **Fetched ahead of time.** `radio:listen` looks up each artist as their song comes on, so most clicks open instantly. If a lookup is still needed, the page shows a "please wait" overlay.
- **Polite to MusicBrainz** (`MusicBrainzClient`): every request names the app and a contact address, requests are at least 1.1 seconds apart, and a "slow down" reply (503) is retried once.
- **Careful matching** (`Names`): a result must match the name or an alias. For "A [+] B" or "A, B & C", the full name is tried first, then the first artist.

## Music and portfolio pages

- **Artist names** are searched by alias as well as name, so "Matchbox 20" finds Matchbox Twenty.
- **Artist pages** add Wikipedia text and photos. Covers come from the Cover Art Archive, then Apple. Wikipedia's album covers are non-free, so they are not used.
- **Song previews** use Apple, then Deezer. Deezer addresses expire within minutes, so only the track ID is saved and a fresh address is fetched when someone presses play. Google Play Music closed in 2020, and Spotify stopped giving new apps previews in 2024, so Spotify and YouTube Music are search links.
- **Listen live** plays each station directly from iHeart or Triton, including its ads.
- **Charts** counts every play the listener hears, per station and per week.
- **Concerts** lists Cincinnati, Dayton, and Columbus shows. Set `TICKETMASTER_API_KEY` in `.env`.
- **Agenda** reads iCal calendar links from `CALENDAR_URLS`, separated by commas. It is public on this build, because there is no login, so only add calendars you are happy to share.
- **Spotify** on the homepage shows playlists from `SPOTIFY_PLAYLIST_URL` (several, separated by commas) and what is playing, with top artists and tracks on the Listening page.

There is no login or admin area. To-dos and notes are read-only: nobody can add, change, or delete them from the site, and their data is meant to arrive from an outside API. The About page is edited in `config/hub.php`.

To connect Spotify, add `SPOTIFY_CLIENT_ID` and `SPOTIFY_CLIENT_SECRET` to `.env`, add `http://127.0.0.1:8002/spotify/callback` as a redirect URI on your Spotify app, then run `docker compose exec web php artisan spotify:connect` and open the link it prints. `php artisan artists:update` fills in artists saved before these features existed.

## Keys and links

Everything here is optional. A feature without its setting explains itself on the page.

| Setting | Used for | Where to get it |
|---------|----------|-----------------|
| `SPOTIFY_PLAYLIST_URL` | The Spotify player on the homepage. Separate several with commas. | The share link of a public playlist |
| `SPOTIFY_CLIENT_ID`, `SPOTIFY_CLIENT_SECRET` | What is playing, and the Listening page | [developer.spotify.com/dashboard](https://developer.spotify.com/dashboard). The app owner needs Spotify Premium. |
| `SPOTIFY_REDIRECT_URI` | Where Spotify returns after you connect | Add the same address to your Spotify app. Spotify does not accept `localhost`. |
| `TICKETMASTER_API_KEY` | Concerts | [developer.ticketmaster.com](https://developer.ticketmaster.com/) (the Consumer Key) |
| `CALENDAR_URLS` | Agenda. Separate several with commas. | Google: Settings > your calendar > Integrate calendar > **Secret address in iCal format**. Apple: turn on **Public Calendar**, then copy its share link. |
| `GITHUB_USERNAME` | Recent work on the About page | Your GitHub username |

Treat calendar links like passwords: anyone who has one can read that calendar.

`DJANGO_SITE_URL`, `NEXTJS_SITE_URL`, and `LARAVEL_SITE_URL` turn the headings on the How it's built page into links to each live site. A heading stays plain text if its address is empty.

## Design

All three builds share one look: a dark page with stage lights sweeping behind see-through cards, drifting music notes, and an equalizer under the current menu item. The homepage cards are numbered like the sides of an album. Fonts are Bricolage Grotesque, Instrument Sans, and Martian Mono from Google Fonts. Animations stop for visitors who ask their device to reduce motion.

## Run with Docker

1. `cp .env.example .env`, then set `DB_PASSWORD`, an `APP_KEY` (`php artisan key:generate --show` prints one), and random values for `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`.
2. `docker compose up -d --build`
3. Open http://localhost:8002

Migrations run automatically when the container starts.

To reach it over HTTPS on your own devices with Tailscale, forward a port to the site and one to Reverb, and add the address to `REVERB_ALLOWED_ORIGINS`:

```sh
tailscale serve --bg --https=8010 http://127.0.0.1:8002
tailscale serve --bg --https=8082 http://127.0.0.1:8082
```

The browser connects to Reverb at whatever address the page was opened from, on `REVERB_PUBLIC_PORT`.

Stop it with `docker compose down`.

## Tests

The tests use an in-memory SQLite database (see `phpunit.xml`), so they need no running containers:

```sh
docker run --rm -v "$PWD":/app -v /app/vendor -w /app composer:2 sh -c \
  "composer install --no-scripts --ignore-platform-reqs && composer dump-autoload && php vendor/bin/phpunit --testsuite Feature"
```

The tests read `APP_KEY` from `.env`, so create that file first. See step 1 under Run with Docker.

Code style check: `vendor/bin/pint --test` (same container).

## Stack

Laravel 13 · Laravel Reverb 1.12 · Laravel Echo 2.6 · PHP 8.5 · Apache · Postgres 17 · ics-parser · Docker Compose
