<?php

namespace App\Modules;

use App\Services\SpotifyClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class ListeningModule extends HubModule
{
    public string $slug = 'listening';

    public string $title = 'Listening';

    public string $summary = 'Top artists and tracks from Spotify.';

    public bool $onHome = false;

    private const RANGES = ['short_term' => 'Last 4 weeks', 'medium_term' => 'Last 6 months', 'long_term' => 'All time'];

    public function __construct(private SpotifyClient $spotify) {}

    public function load(Request $request): array
    {
        $chosen = array_key_exists((string) $request->query('range'), self::RANGES) ? $request->query('range') : 'short_term';
        $context = ['ranges' => self::RANGES, 'chosen' => $chosen];

        try {
            $stats = $this->spotify->topItems($chosen);
        } catch (ConnectionException|RequestException) {
            return [...$context, 'error' => 'Spotify did not respond. Try again shortly.'];
        }

        return $stats === null ? [...$context, 'error' => 'Spotify is not connected yet.'] : [...$context, ...$stats];
    }
}
