<?php

namespace App\Modules;

use App\Services\OpenMeteoClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class WeatherModule extends HubModule
{
    public string $slug = 'weather';

    public string $title = 'Weather';

    public string $summary = 'Current conditions and a 5-day forecast for any city.';

    public string $defaultCity = 'London';

    /** WMO weather codes used by Open-Meteo, grouped into plain labels. */
    private const WEATHER_CODES = [
        0 => 'Clear sky',
        1 => 'Mainly clear', 2 => 'Partly cloudy', 3 => 'Overcast',
        45 => 'Fog', 48 => 'Fog',
        51 => 'Light drizzle', 53 => 'Drizzle', 55 => 'Heavy drizzle',
        61 => 'Light rain', 63 => 'Rain', 65 => 'Heavy rain',
        71 => 'Light snow', 73 => 'Snow', 75 => 'Heavy snow',
        80 => 'Rain showers', 81 => 'Rain showers', 82 => 'Heavy rain showers',
        95 => 'Thunderstorm', 96 => 'Thunderstorm with hail', 99 => 'Thunderstorm with hail',
    ];

    /** Countries and territories that report the weather in Fahrenheit. */
    private const FAHRENHEIT_COUNTRIES = ['US', 'PR', 'GU', 'VI', 'AS', 'MP', 'BS', 'BZ', 'KY', 'LR', 'PW', 'FM', 'MH'];

    public function __construct(private OpenMeteoClient $client) {}

    public function load(Request $request): array
    {
        $searched = trim((string) $request->query('city'));
        $coordinates = $searched === '' ? $this->coordinates($request) : null;
        $city = $coordinates ? '' : ($searched ?: $this->defaultCity);

        try {
            if ($coordinates) {
                $place = $this->client->placeAt(...$coordinates);
                if ($place === null) {
                    return ['city' => $city, 'error' => 'No town found near your location.'];
                }
                $city = $place['name'];
            } else {
                $place = $this->client->findPlace($city);
                if ($place === null) {
                    return ['city' => $city, 'error' => "No place found called “{$city}”."];
                }
            }
            $imperial = in_array($place['country_code'] ?? null, self::FAHRENHEIT_COUNTRIES, true);
            $data = $this->client->forecast($place['latitude'], $place['longitude'], $imperial);
        } catch (ConnectionException|RequestException) {
            return ['city' => $city, 'error' => 'The weather service did not respond. Try again shortly.'];
        }

        $current = $data['current'];
        $daily = $data['daily'];

        $days = [];
        foreach ($daily['time'] as $i => $date) {
            $days[] = [
                'date' => $date,
                'label' => $this->label($daily['weather_code'][$i]),
                'high' => $daily['temperature_2m_max'][$i],
                'low' => $daily['temperature_2m_min'][$i],
            ];
        }

        return [
            'city' => $city,
            'place' => $place,
            'temperature_unit' => $imperial ? '°F' : '°C',
            'wind_unit' => $imperial ? 'mph' : 'km/h',
            'current' => [
                'temperature' => $current['temperature_2m'],
                'feels_like' => $current['apparent_temperature'],
                'wind' => $current['wind_speed_10m'],
                'label' => $this->label($current['weather_code']),
            ],
            'days' => $days,
        ];
    }

    /**
     * The visitor's position from the URL, or null if it is missing or invalid.
     *
     * @return array{float, float}|null
     */
    private function coordinates(Request $request): ?array
    {
        $latitude = $request->query('lat');
        $longitude = $request->query('lon');
        if (! is_numeric($latitude) || ! is_numeric($longitude)) {
            return null;
        }

        return abs((float) $latitude) <= 90 && abs((float) $longitude) <= 180
            ? [(float) $latitude, (float) $longitude]
            : null;
    }

    private function label(int $code): string
    {
        return self::WEATHER_CODES[$code] ?? 'Unknown';
    }
}
