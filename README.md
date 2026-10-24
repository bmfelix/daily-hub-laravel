# Daily Hub · Laravel

A personal dashboard. Each menu link is one module, and each module is one class that calls its own API or database table.

This is one of three builds of the same app (Django, Next.js, Laravel), so the frameworks can be compared side by side.

## Modules

| Page     | Class            | Data source                                   |
|----------|------------------|-----------------------------------------------|
| Weather  | `WeatherModule`  | [Open-Meteo](https://open-meteo.com) (no key) |
| News     | `NewsModule`     | [Hacker News search API](https://hn.algolia.com/api) (no key) |
| To-do    | `TodoModule`     | Postgres (`Task` model)                       |
| Currency | `CurrencyModule` | [Frankfurter](https://frankfurter.dev) (no key) |
| Notes    | `NotesModule`    | Postgres (`Note` model)                       |
| Radio    | `RadioModule`    | [iHeartRadio](https://www.iheart.com) song history (WEBN) and [Triton Digital](https://www.tritondigital.com) now-playing feed (WKRQ), no keys |

Modules can also give the homepage a live card instead of their summary. The Radio card shows the song playing now on each station. WEBN's newest song can appear about a minute after it starts, so its card may briefly say "Last played".

## How it is built

- `app/Modules/HubModule.php` defines the abstract `HubModule` class. Each module returns its page data from `load()`. Modules with forms also override `submit()`.
- API modules get a client class (for example `App\Services\OpenMeteoClient`) through constructor injection. The clients use Laravel's `Http` client, so tests replace every call with `Http::fake()` and run without the internet.
- `config/hub.php` holds the module list. `App\Modules\ModuleRegistry` builds the modules from it, and the routes, the menu, and the homepage cards all come from the registry. To add a page, write a module class and a Blade view in `resources/views/modules/`, then add the class to `config/hub.php`.
- The menu collapses into a "Menu" button below 640px wide.
- No Vite build is needed: the stylesheet is plain CSS in `public/css/style.css`.

## Live updates

The Radio card on the homepage updates without a reload, using **Laravel Reverb** (Laravel's WebSocket server):

1. The **listener** container (`php artisan radio:listen`, `App\Services\Radio\RadioListener`) asks both stations for their song every 15 seconds. The feeds cannot be subscribed to, so it polls.
2. When a song changes (or the current song ends), it dispatches `App\Events\SongChanged`, a broadcast event carrying the re-rendered card, on the public `radio` channel.
3. The **reverb** container (`php artisan reverb:start`) pushes the event to every connected browser.
4. The homepage loads **Laravel Echo** and Pusher JS from a CDN, listens for `.song.changed`, and swaps in the new card.

Two Reverb addresses are configured: `REVERB_HOST`/`REVERB_PORT` is how the app reaches Reverb inside Docker (`reverb:8080`), and `REVERB_PUBLIC_*` is how browsers reach it (`localhost:8082`). `REVERB_ALLOWED_ORIGINS` limits which sites may connect.

Note: Reverb 1.12 needs Guzzle 7, so Guzzle is held at 7 until a Reverb release supports Guzzle 8.

## Artist pages

Click an artist on the homepage card or the Radio page to see everything MusicBrainz has on them: photo, origin, active years, rating, genres, members (with roles and years), the full discography with cover art, links (listen, official, social, concerts, buy, reference), aliases, related artists, and identifiers.

- **Fetched once, then saved** in Postgres: `artists` (the full MusicBrainz record and every release) and `artist_names` (which radio name matched which artist, including names with no match). Saved artists are refreshed after 30 days; unmatched names are retried after 7. See `App\Services\MusicBrainz\ArtistLibrary`.
- **Fetched ahead of time.** `radio:listen` looks up each artist as their song comes on, so most clicks open instantly. If a lookup is still needed, the page shows a "please wait" overlay.
- **Polite to MusicBrainz** (`MusicBrainzClient`): every request names the app and a contact address, requests are at least 1.1 seconds apart, and a "slow down" reply (503) is retried once.
- **Careful matching** (`Names`): a result must match the name or an alias. For "A [+] B" or "A, B & C", the full name is tried first, then the first artist.
- Photos come from Wikimedia Commons and covers from the Cover Art Archive; both are linked from MusicBrainz.

## Run with Docker

1. `cp .env.example .env`, then set `DB_PASSWORD`, an `APP_KEY` (`php artisan key:generate --show` prints one), and random values for `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`.
2. `docker compose up -d --build`
3. Open http://localhost:8002

Migrations run automatically when the container starts.

Stop it with `docker compose down`.

## Tests

The tests use an in-memory SQLite database (see `phpunit.xml`), so they need no running containers:

```sh
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c "composer install && php artisan test"
```

Code style check: `vendor/bin/pint --test` (same container).

## Stack

Laravel 13 · Laravel Reverb 1.12 · Laravel Echo 2.6 · PHP 8.5 · Apache · Postgres 17 · Docker Compose
