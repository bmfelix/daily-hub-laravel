<?php

namespace App\Modules;

use App\Services\GoogleNewsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;
use UnexpectedValueException;

class NewsModule extends HubModule
{
    public string $slug = 'news';

    public string $title = 'News';

    public bool $liveCard = true;

    public string $summary = 'Top stories from Google News.';

    public function __construct(private GoogleNewsClient $client) {}

    public function load(Request $request): array
    {
        try {
            $items = $this->client->topStories();
        } catch (ConnectionException|RequestException|UnexpectedValueException) {
            return ['error' => 'The news service did not respond. Try again shortly.'];
        }

        $stories = array_map(fn (array $item) => [
            'title' => $this->headline($item),
            'url' => $item['link'],
            'source' => $item['source'],
            'published' => $this->published($item['published']),
        ], $items);

        return ['stories' => $stories];
    }

    /**
     * Google adds " - Source" to every title; the page shows the source on its own line instead.
     *
     * @param  array{title: string, source: string}  $item
     */
    private function headline(array $item): string
    {
        $suffix = " - {$item['source']}";

        return $item['source'] !== '' && str_ends_with($item['title'], $suffix)
            ? substr($item['title'], 0, -strlen($suffix))
            : $item['title'];
    }

    private function published(string $date): ?Carbon
    {
        if ($date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
