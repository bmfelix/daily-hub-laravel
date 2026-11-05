<?php

namespace App\Http\Controllers;

use App\Modules\HubModule;
use App\Modules\ModuleRegistry;
use App\Services\SpotifyClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function __construct(private ModuleRegistry $modules) {}

    public function home(): View
    {
        $shown = array_filter($this->modules->all(), fn (HubModule $module) => $module->onHome);
        $cards = array_map(
            fn (HubModule $module) => ['module' => $module, 'view' => $module->cardView, 'data' => $module->card()],
            array_values($shown),
        );

        return view('home', ['cards' => $cards, 'playlists' => SpotifyClient::playlistEmbeds()]);
    }

    public function show(Request $request, string $slug): View
    {
        return $this->module($slug)->show($request);
    }

    private function module(string $slug): HubModule
    {
        return $this->modules->find($slug) ?? abort(404);
    }
}
