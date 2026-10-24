<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Talks to Frankfurter, a free exchange-rate API that needs no key.
 */
class FrankfurterClient
{
    private const API_URL = 'https://api.frankfurter.dev/v1';

    /**
     * @return array<string, string> currency code => name
     */
    public function currencies(): array
    {
        return Http::timeout(8)->get(self::API_URL.'/currencies')->throw()->json();
    }

    /**
     * @return array{rate: float, result: float, date: string}
     */
    public function convert(float $amount, string $source, string $target): array
    {
        $data = Http::timeout(8)
            ->get(self::API_URL.'/latest', ['base' => $source, 'symbols' => $target])
            ->throw()
            ->json();

        $rate = (float) $data['rates'][$target];

        return [
            'rate' => $rate,
            'result' => $amount * $rate,
            'date' => $data['date'],
        ];
    }
}
