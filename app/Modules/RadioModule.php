<?php

namespace App\Modules;

use App\Services\Radio\IHeartStation;
use App\Services\Radio\Station;
use App\Services\Radio\TritonStation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
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
        return ['stations' => $this->stationData(10)];
    }

    public function card(): array
    {
        return ['stations' => $this->stationData(1)];
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
