<?php

namespace App\Modules;

use App\Models\Play;
use App\Models\Song;
use App\Services\MusicBrainz\Names;
use App\Services\Radio\IHeartStation;
use App\Services\Radio\Station;
use App\Services\Radio\TritonStation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use UnexpectedValueException;

class RadioModule extends HubModule
{
    public string $slug = 'radio';

    public string $title = 'Radio';

    public string $summary = 'What is playing on Cincinnati radio.';

    public string $cardView = 'cards.radio';

    private const UNAVAILABLE = 'Song information is not available right now.';

    /** @var list<Station> */
    private array $stations;

    /**
     * @param  list<Station>|null  $stations
     */
    public function __construct(?array $stations = null)
    {
        $this->stations = $stations ?? [
            new IHeartStation('WEBN', '102.7 FM', 1701),
            new TritonStation('WKRQ', 'Q102 · 101.9 FM', 'WKRQFM'),
        ];
    }

    public function load(Request $request): array
    {
        return ['stations' => $this->withSavedSongs($this->stationData(10))];
    }

    public function card(): array
    {
        return ['stations' => $this->withSavedSongs($this->stationData(1))];
    }

    /**
     * Save every recent play for the charts, and return [artist, title] for each.
     *
     * @return list<array{string, string}>
     */
    public function rememberRecent(): array
    {
        $plays = [];
        foreach ($this->stationData(10) as $item) {
            foreach ($item['tracks'] ?? [] as $track) {
                if ($track['artist'] !== '' && $track['title'] !== '') {
                    $plays[] = [
                        'station' => $item['station']->name,
                        'artist_name' => $track['artist'],
                        'title' => $track['title'],
                        'song_key' => Names::songKey($track['artist'], $track['title']),
                        'played_at' => Carbon::createFromTimestamp($track['start'], config('app.timezone'))->toDateTimeString(),
                    ];
                }
            }
        }
        Play::insertOrIgnore($plays); // plays already saved are skipped

        return array_map(fn ($play) => [$play['artist_name'], $play['title']], $plays);
    }

    /**
     * Add each track's saved previews. The listener looks songs up, so pages stay fast.
     *
     * @param  list<array<string, mixed>>  $stations
     * @return list<array<string, mixed>>
     */
    private function withSavedSongs(array $stations): array
    {
        $keys = [];
        foreach ($stations as $item) {
            foreach ($item['tracks'] ?? [] as $track) {
                $keys[] = Names::songKey($track['artist'], $track['title']);
            }
        }
        $songs = Song::whereIn('key', $keys)->get()->keyBy('key');

        foreach ($stations as &$item) {
            foreach ($item['tracks'] ?? [] as $i => $track) {
                $item['tracks'][$i]['song'] = $songs[Names::songKey($track['artist'], $track['title'])] ?? null;
            }
        }

        return $stations;
    }

    /**
     * @return list<array{station: Station, tracks?: array, error?: string}>
     */
    private function stationData(int $limit): array
    {
        return array_map(function (Station $station) use ($limit) {
            try {
                return ['station' => $station, 'tracks' => $station->recent($limit)];
            } catch (ConnectionException|RequestException|UnexpectedValueException) {
                return ['station' => $station, 'error' => self::UNAVAILABLE];
            }
        }, $this->stations);
    }
}
