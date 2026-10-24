<?php

namespace App\Services\Radio;

use App\Events\SongChanged;
use App\Modules\RadioModule;

/**
 * Checks the stations and broadcasts the Radio card when a song changes.
 *
 * The station feeds cannot be subscribed to, so the listener asks on a timer.
 */
class RadioListener
{
    public ?string $lastSeen = null;

    /** @var array{stations: list<array<string, mixed>>}|null */
    public ?array $lastCard = null;

    public function __construct(private RadioModule $radio) {}

    /**
     * Broadcast if anything changed since the last check. Returns true when it broadcast.
     */
    public function check(): bool
    {
        $card = $this->radio->card();
        $this->lastCard = $card;
        $seen = $this->signature($card);
        if ($seen === $this->lastSeen) {
            return false;
        }
        SongChanged::dispatch(view($this->radio->cardView, $card)->render());
        // Remember only after a successful send, so a failed broadcast is retried.
        $this->lastSeen = $seen;

        return true;
    }

    /**
     * The artist names on air at the last check.
     *
     * @return list<string>
     */
    public function currentArtists(): array
    {
        return collect($this->lastCard['stations'] ?? [])
            ->map(fn (array $item) => $item['tracks'][0]['artist'] ?? null)
            ->filter()->values()->all();
    }

    /**
     * What a viewer can see change: each station's song and whether it is still playing.
     *
     * @param  array{stations: list<array{station: Station, tracks?: array, error?: string}>}  $card
     */
    private function signature(array $card): string
    {
        return json_encode(array_map(function (array $item) {
            $track = $item['tracks'][0] ?? null;

            return [$item['station']->name, $track ? [$track['title'], $track['artist'], $track['playing']] : null];
        }, $card['stations']));
    }
}
