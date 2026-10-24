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
