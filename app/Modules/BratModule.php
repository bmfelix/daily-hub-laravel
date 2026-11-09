<?php

namespace App\Modules;

use Illuminate\Http\Request;

class BratModule extends HubModule
{
    public string $slug = 'brat';

    public string $title = 'BRAT';

    public string $summary = 'A World of Warcraft add-on that shows the spell to press now and next.';

    public bool $onHome = false;

    public function load(Request $request): array
    {
        return [];
    }
}
