<?php

namespace App\Modules;

use App\Services\FrankfurterClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;

class CurrencyModule extends HubModule
{
    public string $slug = 'currency';

    public string $title = 'Currency';

    public string $summary = "Convert between currencies with today's exchange rates.";

    private const SERVICE_DOWN = 'The exchange-rate service did not respond. Try again shortly.';

    public function __construct(private FrankfurterClient $client) {}

    public function load(Request $request): array
    {
        $data = [
            'amount' => trim((string) $request->query('amount', '100')),
            'source' => (string) $request->query('from', 'USD'),
            'target' => (string) $request->query('to', 'EUR'),
        ];

        try {
            $data['currencies'] = $this->client->currencies();
        } catch (ConnectionException|RequestException) {
            return [...$data, 'error' => self::SERVICE_DOWN];
        }

        if (! is_numeric($data['amount'])) {
            return [...$data, 'error' => 'Enter a number for the amount.'];
        }
        if (! isset($data['currencies'][$data['source']], $data['currencies'][$data['target']])) {
            return [...$data, 'error' => 'Choose currencies from the list.'];
        }
        if ($data['source'] === $data['target']) {
            return [...$data, 'conversion' => ['rate' => 1.0, 'result' => (float) $data['amount'], 'date' => null]];
        }

        try {
            $data['conversion'] = $this->client->convert((float) $data['amount'], $data['source'], $data['target']);
        } catch (ConnectionException|RequestException) {
            $data['error'] = self::SERVICE_DOWN;
        }

        return $data;
    }
}
