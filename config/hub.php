<?php

use App\Modules\CurrencyModule;
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
        CurrencyModule::class,
        NotesModule::class,
        RadioModule::class,
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

];
