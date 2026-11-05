<?php

namespace App\Modules;

use App\Models\Play;
use App\Models\Song;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class ChartsModule extends HubModule
{
    public string $slug = 'charts';

    public string $title = 'Charts';

    public string $summary = 'The most-played songs and artists on Cincinnati radio, week by week.';

    public string $cardView = 'cards.charts';

    private const CHART_SIZE = 10;

    public function card(): array
    {
        $plays = $this->week(now()->startOfWeek(Carbon::MONDAY));

        return ['songs' => $this->topSongs($plays, 3), 'total' => (clone $plays)->count()];
    }

    public function load(Request $request): array
    {
        $thisWeek = now()->startOfWeek(Carbon::MONDAY);
        try {
            $monday = Carbon::createFromFormat('Y-m-d', (string) $request->query('week'))->startOfWeek(Carbon::MONDAY);
        } catch (Throwable) {
            $monday = $thisWeek->copy();
        }

        $plays = $this->week($monday);
        $firstPlay = Play::min('played_at');
        $names = (clone $plays)->distinct()->orderBy('station')->pluck('station');

        return [
            'monday' => $monday,
            'sunday' => $monday->copy()->addDays(6),
            'previousWeek' => $firstPlay && $monday->gt(Carbon::parse($firstPlay)) ? $monday->copy()->subWeek() : null,
            'nextWeek' => $monday->lt($thisWeek) ? $monday->copy()->addWeek() : null,
            'total' => (clone $plays)->count(),
            'stations' => $names->map(fn ($name) => [
                'name' => $name,
                'songs' => $this->topSongs((clone $plays)->where('station', $name)),
                'artists' => $this->topArtists((clone $plays)->where('station', $name)),
            ])->all(),
        ];
    }

    private function week(Carbon $monday): Builder
    {
        return Play::query()->where('played_at', '>=', $monday)->where('played_at', '<', $monday->copy()->addWeek());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topSongs(Builder $plays, int $limit = self::CHART_SIZE): array
    {
        $rows = (clone $plays)->selectRaw('song_key, count(*) as plays, min(title) as name, min(artist_name) as artist')
            ->groupBy('song_key')->orderByDesc('plays')->orderBy('name')->limit($limit)->get();
        $songs = Song::whereIn('key', $rows->pluck('song_key'))->get()->keyBy('key');

        return $this->withBars($rows->map(fn ($row) => [
            'title' => $row->name, 'artist' => $row->artist, 'plays' => (int) $row->plays, 'song' => $songs[$row->song_key] ?? null,
        ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function topArtists(Builder $plays): array
    {
        $rows = (clone $plays)->selectRaw('artist_name, count(*) as plays')
            ->groupBy('artist_name')->orderByDesc('plays')->orderBy('artist_name')->limit(self::CHART_SIZE)->get();

        return $this->withBars($rows->map(fn ($row) => ['artist' => $row->artist_name, 'plays' => (int) $row->plays])->all());
    }

    /**
     * Add how long each row's bar is, compared to the top of the chart.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function withBars(array $rows): array
    {
        $top = $rows[0]['plays'] ?? 1;

        return array_map(fn ($row) => [...$row, 'bar' => (int) round($row['plays'] / $top * 100)], $rows);
    }
}
