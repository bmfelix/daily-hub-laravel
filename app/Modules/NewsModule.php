<?php

namespace App\Modules;

use App\Services\HackerNewsClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class NewsModule extends HubModule
{
    public string $slug = 'news';

    public string $title = 'News';

    public string $summary = 'Top headlines from the Hacker News front page.';

    public function __construct(private HackerNewsClient $client) {}

    public function load(Request $request): array
    {
        try {
            $hits = $this->client->frontPage();
        } catch (ConnectionException|RequestException) {
            return ['error' => 'The news service did not respond. Try again shortly.'];
        }

        $stories = array_map(function (array $hit) {
            $discussion = "https://news.ycombinator.com/item?id={$hit['objectID']}";

            return [
                'title' => $hit['title'],
                'url' => ($hit['url'] ?? '') ?: $discussion,
                'discussion' => $discussion,
                'points' => $hit['points'] ?? 0,
                'comments' => $hit['num_comments'] ?? 0,
                'author' => $hit['author'],
            ];
        }, $hits);

        return ['stories' => $stories];
    }
}
