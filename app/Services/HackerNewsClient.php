<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Talks to the Hacker News search API (by Algolia), which needs no key.
 */
class HackerNewsClient
{
    private const SEARCH_URL = 'https://hn.algolia.com/api/v1/search';

    /**
     * @return list<array<string, mixed>>
     */
    public function frontPage(int $limit = 20): array
    {
        return Http::timeout(8)
            ->get(self::SEARCH_URL, ['tags' => 'front_page', 'hitsPerPage' => $limit])
            ->throw()
            ->json('hits');
    }
}
