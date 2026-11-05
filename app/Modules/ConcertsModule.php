<?php

namespace App\Modules;

use App\Services\TicketmasterClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ConcertsModule extends HubModule
{
    public string $slug = 'concerts';

    public string $title = 'Concerts';

    public string $summary = 'Upcoming shows in Cincinnati, Dayton, and Columbus.';

    public bool $liveCard = true;

    private const CITIES = ['Cincinnati' => 'OH', 'Dayton' => 'OH', 'Columbus' => 'OH'];

    private const CACHE_SECONDS = 21600; // listings change slowly, and the free key has a daily limit

    public function __construct(private TicketmasterClient $client) {}

    public function load(Request $request): array
    {
        $names = array_keys(self::CITIES);
        $chosen = in_array($request->query('city'), $names, true) ? $request->query('city') : '';
        $context = ['cities' => $names, 'chosen' => $chosen];
        if (! config('hub.ticketmaster_key')) {
            return [...$context, 'error' => 'Concert listings need a Ticketmaster API key.'];
        }

        $concerts = [];
        foreach (self::CITIES as $city => $state) {
            if ($chosen === '' || $chosen === $city) {
                try {
                    array_push($concerts, ...$this->cityEvents($city, $state));
                } catch (ConnectionException|RequestException) {
                    return [...$context, 'error' => 'The concert service did not respond. Try again shortly.'];
                }
            }
        }
        $concerts = array_values(array_filter($concerts, fn ($show) => $show['date'] !== ''));
        usort($concerts, fn ($a, $b) => [$a['date'], $a['time']] <=> [$b['date'], $b['time']]);

        return [...$context, 'concerts' => $concerts];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cityEvents(string $city, string $state): array
    {
        return Cache::remember("concerts-{$city}", self::CACHE_SECONDS, fn () => array_map(
            fn ($event) => $this->concert($event, $city),
            $this->client->events($city, $state),
        ));
    }

    /**
     * The parts of a Ticketmaster event the page shows.
     *
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function concert(array $event, string $city): array
    {
        $images = collect($event['images'] ?? [])->sortBy('width')->values();
        $image = $images->first(fn ($item) => ($item['width'] ?? 0) >= 300) ?? $images->last();

        return [
            'name' => $event['name'] ?? '',
            'url' => $event['url'] ?? '',
            'date' => $event['dates']['start']['localDate'] ?? '',
            'time' => $event['dates']['start']['localTime'] ?? '',
            'venue' => $event['_embedded']['venues'][0]['name'] ?? '',
            'city' => $city,
            'image' => $image['url'] ?? '',
        ];
    }
}
