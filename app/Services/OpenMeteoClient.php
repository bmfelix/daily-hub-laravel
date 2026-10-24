<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Talks to Open-Meteo, a free weather API that needs no key.
 */
class OpenMeteoClient
{
    private const GEOCODE_URL = 'https://geocoding-api.open-meteo.com/v1/search';

    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    private const TIMEOUT = 8;

    /**
     * @return array{name: string, country?: string, latitude: float, longitude: float}|null
     */
    public function findPlace(string $name): ?array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->get(self::GEOCODE_URL, ['name' => $name, 'count' => 1])
            ->throw();

        return $response->json('results.0');
    }

    /**
     * @return array<string, mixed>
     */
    public function forecast(float $latitude, float $longitude): array
    {
        return Http::timeout(self::TIMEOUT)
            ->get(self::FORECAST_URL, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,apparent_temperature,wind_speed_10m,weather_code',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                'timezone' => 'auto',
                'forecast_days' => 5,
            ])
            ->throw()
            ->json();
    }
}
