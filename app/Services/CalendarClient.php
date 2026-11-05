<?php

namespace App\Services;

use Carbon\CarbonInterface;
use ICal\ICal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Reads calendars from their iCal addresses, which Apple and Google both offer.
 */
class CalendarClient
{
    /**
     * @return list<array{title: string, location: string, all_day: bool, day: string, time: string}>
     */
    public function events(string $url, CarbonInterface $start, CarbonInterface $end): array
    {
        $body = Http::timeout(10)->get(str_replace('webcal://', 'https://', trim($url)))->throw()->body();
        $calendar = new ICal(false, ['defaultTimeZone' => config('app.timezone')]);
        $calendar->initString($body);

        $events = [];
        foreach ($calendar->eventsFromRange($start->toDateTimeString(), $end->toDateTimeString()) as $event) {
            $allDay = strlen((string) $event->dtstart) === 8;
            $when = $allDay
                ? Carbon::parse($event->dtstart, config('app.timezone'))
                : Carbon::instance($calendar->iCalDateToDateTime($event->dtstart_array[3]))->setTimezone(config('app.timezone'));
            $events[] = [
                'title' => (string) ($event->summary ?? 'Untitled'),
                'location' => (string) ($event->location ?? ''),
                'all_day' => $allDay,
                'day' => $when->toDateString(),
                'time' => $allDay ? '' : $when->format('H:i'),
            ];
        }

        return $events;
    }
}
