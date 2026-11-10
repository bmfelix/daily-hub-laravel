<?php

namespace App\Services\Radio;

/**
 * One radio station. Subclasses read song data from their broadcaster's API.
 */
abstract class Station
{
    public function __construct(
        public readonly string $name,
        public readonly string $frequency,
    ) {}

    /**
     * The station's live audio.
     */
    abstract public function streamUrl(): string;

    /**
     * Where the station sits on an 88-108 FM dial, from 0 to 100. Reads the last number in the frequency.
     */
    public function dial(): float
    {
        if (! preg_match_all('/\d+(?:\.\d+)?/', $this->frequency, $matches)) {
            return 50.0;
        }

        return round(min(max(((float) end($matches[0]) - 88) / 20 * 100, 0), 100), 1);
    }

    /**
     * Recent songs, newest first. Each subclass calls its own API here.
     *
     * @return list<array{title: string, artist: string, image: string, start: int|float, end: int|float}>
     */
    abstract protected function tracks(int $limit): array;

    /**
     * Recent songs, newest first. The newest is marked as playing if it has not ended yet.
     *
     * @return list<array{title: string, artist: string, image: string, start: int|float, end: int|float, playing: bool}>
     */
    public function recent(int $limit = 10): array
    {
        $now = microtime(true);
        $tracks = [];
        foreach ($this->tracks($limit) as $i => $track) {
            $tracks[] = [...$track, 'playing' => $i === 0 && $now < $track['end']];
        }

        return $tracks;
    }
}
