<?php

use App\Http\Controllers\ArtistController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\SongController;
use App\Http\Controllers\SpotifyController;
use App\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/', [ModuleController::class, 'home'])->name('home');
Route::get('/artists', [ArtistController::class, 'lookup'])->name('artists.lookup');
Route::get('/artists/{mbid}', [ArtistController::class, 'show'])->whereUuid('mbid')->name('artists.show');
Route::get('/songs/{song}/preview', [SongController::class, 'preview'])->name('songs.preview');
Route::get('/spotify/card', [SpotifyController::class, 'card'])->name('spotify.card');
Route::get('/spotify/callback', [SpotifyController::class, 'callback'])->name('spotify.callback');

// One page per module in config/hub.php.
foreach (app(ModuleRegistry::class)->all() as $slug => $module) {
    Route::get("/{$slug}", [ModuleController::class, 'show'])->defaults('slug', $slug)->name("modules.{$slug}");
}
