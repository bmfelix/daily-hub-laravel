<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sent when a station's song changes. Carries the re-rendered Radio card.
 */
class SongChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public readonly string $html) {}

    public function broadcastOn(): Channel
    {
        return new Channel('radio');
    }

    public function broadcastAs(): string
    {
        return 'song.changed';
    }

    /**
     * @return array{html: string}
     */
    public function broadcastWith(): array
    {
        return ['html' => $this->html];
    }
}
