<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Talks to Open-Meteo, a free weather API that needs no key, and Nominatim for place names.
 */
class OpenMeteoClient
{
    private const GEOCODE_URL = 'https://geocoding-api.open-meteo.com/v1/search';

    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    private const REVERSE_GEOCODE_URL = 'https://nominatim.openstreetmap.org/reverse';

    private const USER_AGENT = 'DailyHub/1.0';

    private const TIMEOUT = 8;

    /**
     * @return array{name: string, country?: string, country_code?: string, latitude: float, longitude: float}|null
     */
    public function findPlace(string $name): ?array
    {
        $response = Http::timeout(self::TIMEOUT)
            ->get(self::GEOCODE_URL, ['name' => $name, 'count' => 1])
            ->throw();

        return $response->json('results.0');
    }

    /**
     * Name the place at these coordinates. Open-Meteo cannot do this, so Nominatim does.
     *
     * @return array{name: string, country?: string, country_code?: string, latitude: float, longitude: float}|null
     */
    public function placeAt(float $latitude, float $longitude): ?array
    {
        $data = Http::timeout(self::TIMEOUT)
            ->withUserAgent(self::USER_AGENT)
            ->get(self::REVERSE_GEOCODE_URL, [
                'format' => 'jsonv2',
                'lat' => $latitude,
                'lon' => $longitude,
                'zoom' => 10,
                'accept-language' => 'en',
            ])
            ->throw()
            ->json();

        $address = $data['address'] ?? null;
        if (! $address) {
            return null;
        }

        return [
            'name' => $address['city'] ?? $address['town'] ?? $address['village'] ?? $address['hamlet']
                ?? $address['municipality'] ?? $address['county'] ?? $address['state'] ?? $data['name'] ?? '',
            'country' => $address['country'] ?? null,
            'country_code' => strtoupper($address['country_code'] ?? ''),
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forecast(float $latitude, float $longitude, bool $imperial = false): array
    {
        return Http::timeout(self::TIMEOUT)
            ->get(self::FORECAST_URL, [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'current' => 'temperature_2m,apparent_temperature,wind_speed_10m,weather_code',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                'timezone' => 'auto',
                'forecast_days' => 5,
                'temperature_unit' => $imperial ? 'fahrenheit' : 'celsius',
                'wind_speed_unit' => $imperial ? 'mph' : 'kmh',
            ])
            ->throw()
            ->json();
    }
}
