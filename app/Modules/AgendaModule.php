<?php

namespace App\Modules;

use App\Services\CalendarClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AgendaModule extends HubModule
{
    public string $slug = 'agenda';

    public string $title = 'Agenda';

    public string $summary = 'What is on the calendar for the next two weeks.';

    public bool $liveCard = true;

    private const DAYS_AHEAD = 14;

    private const CACHE_SECONDS = 900;

    public function __construct(private CalendarClient $client) {}

    public function load(Request $request): array
    {
        if (! config('hub.calendar_urls')) {
            return ['error' => 'Add your calendar links to see them here.'];
        }

        [$items, $failed] = Cache::get('agenda-events') ?? $this->fetch();
        if (! $failed) {
            Cache::put('agenda-events', [$items, $failed], self::CACHE_SECONDS);
        }

        return [
            'items' => $items,
            'days' => collect($items)->groupBy('day')->map(fn ($entries, $day) => ['date' => $day, 'items' => $entries->all()])->values()->all(),
            'failed' => $failed,
        ];
    }

    /**
     * All events and how many calendars could not be read. One bad calendar does not hide the rest.
     *
     * @return array{list<array<string, mixed>>, int}
     */
    private function fetch(): array
    {
        $items = [];
        $failed = 0;
        foreach (config('hub.calendar_urls') as $url) {
            try {
                array_push($items, ...$this->client->events($url, now()->startOfDay(), now()->startOfDay()->addDays(self::DAYS_AHEAD)));
            } catch (ConnectionException|RequestException) {
                $failed++;
            } catch (Throwable) {
                $failed++; // a calendar file that could not be understood
            }
        }
        usort($items, fn ($a, $b) => [$a['day'], ! $a['all_day'], $a['time']] <=> [$b['day'], ! $b['all_day'], $b['time']]);

        return [$items, $failed];
    }
}
