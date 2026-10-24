<?php

namespace App\Services\Radio;

use Illuminate\Support\Facades\Http;

/**
 * Reads song history from iHeartRadio's public API (no key). The newest song can appear about a minute late.
 */
class IHeartStation extends Station
{
    private const HISTORY_URL = 'https://us.api.iheart.com/api/v3/live-meta/stream/%d/trackHistory';

    public function __construct(string $name, string $frequency, private int $stationId)
    {
        parent::__construct($name, $frequency);
    }

    protected function tracks(int $limit): array
    {
        $items = Http::timeout(5)
            ->get(sprintf(self::HISTORY_URL, $this->stationId), ['limit' => $limit])
            ->throw()
            ->json('data');

        return array_map(fn (array $item) => [
            'title' => trim($item['title']),
            'artist' => trim($item['artist']),
            'image' => preg_replace('#^http://#', 'https://', $item['imagePath'] ?? ''),
            'start' => $item['startTime'],
            'end' => $item['endTime'],
        ], $items);
    }
}
