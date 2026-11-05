<?php

use App\Modules\AboutModule;
use App\Modules\AgendaModule;
use App\Modules\BuiltModule;
use App\Modules\ChartsModule;
use App\Modules\ConcertsModule;
use App\Modules\ListeningModule;
use App\Modules\NewsModule;
use App\Modules\NotesModule;
use App\Modules\RadioModule;
use App\Modules\TodoModule;
use App\Modules\WeatherModule;

return [

    /*
    | Menu order. Add a module class here to give it a page and a menu link.
    */
    'modules' => [
        WeatherModule::class,
        NewsModule::class,
        TodoModule::class,
        NotesModule::class,
        AgendaModule::class,
        RadioModule::class,
        ChartsModule::class,
        ConcertsModule::class,
        ListeningModule::class,
        AboutModule::class,
        BuiltModule::class,
    ],

    /*
    | Where browsers reach Reverb for live updates. Inside Docker, the server
    | reaches it at REVERB_HOST instead (see config/broadcasting.php).
    */
    'reverb_public' => [
        'host' => env('REVERB_PUBLIC_HOST', 'localhost'),
        'port' => (int) env('REVERB_PUBLIC_PORT', 8082),
        'scheme' => env('REVERB_PUBLIC_SCHEME', 'http'),
    ],

    /*
    | Spotify on the homepage. All optional. Spotify does not accept "localhost"
    | as a redirect address.
    */
    'spotify' => [
        'client_id' => env('SPOTIFY_CLIENT_ID', ''),
        'client_secret' => env('SPOTIFY_CLIENT_SECRET', ''),
        'redirect_uri' => env('SPOTIFY_REDIRECT_URI', 'http://127.0.0.1:8002/spotify/callback'),
        'playlist_url' => env('SPOTIFY_PLAYLIST_URL', ''),
    ],

    'ticketmaster_key' => env('TICKETMASTER_API_KEY', ''),

    // Calendar addresses for the Agenda page, separated by commas.
    'calendar_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('CALENDAR_URLS', ''))))),

    'github_username' => env('GITHUB_USERNAME', 'bmfelix'),

    // Where each build runs, for the How it's built page. Empty leaves a heading as plain text.
    'sites' => [
        'Django' => env('DJANGO_SITE_URL', ''),
        'Next.js' => env('NEXTJS_SITE_URL', ''),
        'Laravel' => env('LARAVEL_SITE_URL', ''),
    ],

    /*
    | The About page. There is no admin area, so edit it here.
    */
    'about' => [
        'name' => 'Brad Felix',
        'headline' => '',
        'bio' => '',
        'website' => '',
        'email' => '',
        'projects' => [
            [
                'title' => 'Daily Hub',
                'description' => 'A personal dashboard built three times, in Django, Next.js, and Laravel, to compare the frameworks.',
                'tech' => ['Django', 'Next.js', 'Laravel', 'Postgres', 'Docker'],
                'url' => '',
                'repo_url' => 'https://github.com/bmfelix/daily-hub-laravel',
            ],
        ],
    ],

];
