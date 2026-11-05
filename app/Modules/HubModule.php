<?php

namespace App\Modules;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * One Daily Hub page. Each subclass is one menu link and owns its API calls.
 */
abstract class HubModule
{
    public string $slug;

    public string $title;

    public string $summary;

    /** The Blade view for this module's homepage card. */
    public string $cardView = 'cards.summary';

    /** True: the homepage loads this module's own card after the page opens. */
    public bool $liveCard = false;

    public bool $inMenu = true;

    public bool $onHome = true;

    /**
     * Return the data the page needs. Subclasses call their API here.
     *
     * @return array<string, mixed>
     */
    abstract public function load(Request $request): array;

    /**
     * @param  array<string, mixed>  $query
     */
    public function url(array $query = []): string
    {
        return route("modules.{$this->slug}", $query);
    }

    public function show(Request $request): View
    {
        $view = $this->liveCard && $request->has('card') ? "cards.{$this->slug}-live" : "modules.{$this->slug}";

        return view($view, ['module' => $this, ...$this->load($request)]);
    }

    /**
     * Return the data the homepage card needs. Override to show live data.
     *
     * @return array<string, mixed>
     */
    public function card(): array
    {
        return [];
    }

    /**
     * Handle a form post. Modules without forms keep this default.
     */
    public function submit(Request $request): SymfonyResponse
    {
        abort(Response::HTTP_METHOD_NOT_ALLOWED);
    }
}
