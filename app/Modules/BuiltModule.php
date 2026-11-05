<?php

namespace App\Modules;

use Illuminate\Http\Request;

class BuiltModule extends HubModule
{
    public string $slug = 'built';

    public string $title = "How it's built";

    public string $summary = 'One app, built three times to compare the frameworks.';

    public bool $inMenu = false;

    public bool $onHome = false;

    public function load(Request $request): array
    {
        return [];
    }
}
