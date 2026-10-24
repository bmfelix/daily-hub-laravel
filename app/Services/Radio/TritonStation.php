<?php

namespace App\Services\Radio;

use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

/**
 * Reads live song data from Triton Digital's public now-playing feed (no key).
 */
class TritonStation extends Station
{
    private const NOW_PLAYING_URL = 'https://np.tritondigital.com/public/nowplaying';

    public function __construct(string $name, string $frequency, private string $mount)
    {
        parent::__construct($name, $frequency);
    }

    protected function tracks(int $limit): array
    {
        $xml = Http::timeout(5)
            // Ask for extra: the feed repeats a song when it is cued twice.
            ->get(self::NOW_PLAYING_URL, ['mountName' => $this->mount, 'numberToFetch' => $limit * 2, 'eventType' => 'track'])
            ->throw()
            ->body();

        // Collect XML errors instead of raising PHP warnings, so a bad feed is handled below.
        $previous = libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, options: LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if ($feed === false) {
            throw new UnexpectedValueException('Triton returned invalid XML');
        }

        $tracks = [];
        $lastId = null;
        foreach ($feed->{'nowplaying-info'} as $event) {
            $props = [];
            foreach ($event->property as $property) {
                $props[(string) $property['name']] = (string) $property;
            }

            $start = (int) $props['cue_time_start'] / 1000;
            $track = [
                'title' => trim($props['cue_title'] ?? ''),
                'artist' => trim($props['track_artist_name'] ?? ''),
                'image' => '',
                'start' => $start,
                'end' => $start + (int) ($props['cue_time_duration'] ?? 0) / 1000,
            ];

            if (($props['track_id'] ?? null) === $lastId) {
                $tracks[count($tracks) - 1] = $track; // an older cue of the same song: keep its earlier start
            } else {
                $tracks[] = $track;
            }
            $lastId = $props['track_id'] ?? null;
        }

        return array_slice($tracks, 0, $limit);
    }
}
