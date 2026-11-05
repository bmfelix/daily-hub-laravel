<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Talks to Ticketmaster's Discovery API. Needs a free key in TICKETMASTER_API_KEY.
 */
class TicketmasterClient
{
    private const EVENTS_URL = 'https://app.ticketmaster.com/discovery/v2/events.json';

    /**
     * @return list<array<string, mixed>>
     */
    public function events(string $city, string $state, int $size = 30): array
    {
        return Http::timeout(8)->get(self::EVENTS_URL, [
            'apikey' => config('hub.ticketmaster_key'),
            'classificationName' => 'music',
            'city' => $city,
            'stateCode' => $state,
            'sort' => 'date,asc',
            'size' => $size,
        ])->throw()->json('_embedded.events') ?? [];
    }
}
