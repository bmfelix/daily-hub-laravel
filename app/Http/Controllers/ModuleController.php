<?php

namespace App\Http\Controllers;

use App\Modules\HubModule;
use App\Modules\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModuleController extends Controller
{
    public function __construct(private ModuleRegistry $modules) {}

    public function home(): View
    {
        $cards = array_map(
            fn (HubModule $module) => ['module' => $module, 'view' => $module->cardView, 'data' => $module->card()],
            array_values($this->modules->all()),
        );

        return view('home', ['cards' => $cards]);
    }

    public function show(Request $request, string $slug): View
    {
        return $this->module($slug)->show($request);
    }

    public function submit(Request $request, string $slug): Response
    {
        return $this->module($slug)->submit($request);
    }

    private function module(string $slug): HubModule
    {
        return $this->modules->find($slug) ?? abort(404);
    }
}
