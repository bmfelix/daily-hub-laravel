<?php

namespace App\Services\Music;

use Illuminate\Support\Facades\Http;

/**
 * Artist background from English Wikipedia. Its text is CC BY-SA, so pages credit and link it.
 */
class WikipediaClient
{
    private const API_URL = 'https://en.wikipedia.org/w/api.php';

    private const WIKIDATA_API_URL = 'https://www.wikidata.org/w/api.php';

    private const USER_AGENT = 'DailyHub/1.0 (https://github.com/bmfelix/daily-hub-laravel)';

    // A page found by name only counts if its description sounds like a music act.
    private const MUSIC_WORDS = '/\b(band|singer|musician|rapper|group|duo|trio|songwriter|dj|vocalist|producer|composer|guitarist|supergroup)\b/i';

    /**
     * The Wikipedia page for a MusicBrainz artist record, or null.
     *
     * @param  array<string, mixed>  $record
     * @return array{title: string, url: string, summary: string, description: string, image: string}|null
     */
    public function find(array $record): ?array
    {
        $title = $this->linkedTitle($record);
        $page = $title ? $this->page($title) : null;
        if ($page) {
            return $page;
        }

        $name = $record['name'];
        foreach ([$name, "{$name} (band)", "{$name} (musician)", "{$name} (singer)"] as $candidate) {
            $page = $this->page($candidate);
            if ($page && preg_match(self::MUSIC_WORDS, $page['description'])) {
                return $page;
            }
        }

        return null;
    }

    /**
     * @return array{title: string, url: string, summary: string, description: string, image: string}|null
     */
    public function page(string $title): ?array
    {
        $data = $this->get(self::API_URL, [
            'action' => 'query', 'titles' => $title, 'redirects' => 1,
            'prop' => 'extracts|info|description|pageprops|pageimages',
            'exintro' => 1, 'explaintext' => 1, 'inprop' => 'url', 'ppprop' => 'disambiguation',
            'piprop' => 'thumbnail', 'pithumbsize' => 640, // pageimages only returns free images by default
        ]);
        $page = $data['query']['pages'][0] ?? [];
        if (($page['missing'] ?? false) || isset($page['pageprops']['disambiguation']) || empty($page['extract'])) {
            return null;
        }

        return [
            'title' => $page['title'],
            'url' => $page['fullurl'],
            'summary' => trim($page['extract']),
            'description' => $page['description'] ?? '',
            'image' => $page['thumbnail']['source'] ?? '',
        ];
    }

    /**
     * The page title MusicBrainz links to, directly or through Wikidata.
     *
     * @param  array<string, mixed>  $record
     */
    public function linkedTitle(array $record): ?string
    {
        $wikidata = null;
        foreach ($record['relations'] ?? [] as $relation) {
            $url = $relation['url']['resource'] ?? '';
            if (preg_match('#//en\.wikipedia\.org/wiki/(.+)$#', $url, $direct)) {
                return str_replace('_', ' ', rawurldecode($direct[1]));
            }
            if (preg_match('#wikidata\.org/wiki/(Q\d+)#', $url, $found)) {
                $wikidata = $found[1];
            }
        }
        if ($wikidata === null) {
            return null;
        }
        $data = $this->get(self::WIKIDATA_API_URL, ['action' => 'wbgetentities', 'ids' => $wikidata, 'props' => 'sitelinks', 'sitefilter' => 'enwiki']);

        return $data['entities'][$wikidata]['sitelinks']['enwiki']['title'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function get(string $url, array $params): array
    {
        return Http::withUserAgent(self::USER_AGENT)->timeout(8)
            ->get($url, [...$params, 'format' => 'json', 'formatversion' => 2])
            ->throw()->json() ?? [];
    }
}
