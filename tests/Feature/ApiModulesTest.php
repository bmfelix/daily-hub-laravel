<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Http::fake() answers every API call, so these tests run without the internet.
 */
class ApiModulesTest extends TestCase
{
    private function fakeWeather(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => fn (Request $request) => str_contains($request->url(), 'Nowhere')
                ? Http::response(['generationtime_ms' => 0.1])
                : Http::response(['results' => [['name' => 'Paris', 'country' => 'France', 'latitude' => 48.85, 'longitude' => 2.35]]]),
            'api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 9.2, 'apparent_temperature' => 7, 'wind_speed_10m' => 12, 'weather_code' => 3],
                'daily' => [
                    'time' => ['2026-10-24', '2026-10-25'],
                    'weather_code' => [0, 61],
                    'temperature_2m_max' => [14, 12],
                    'temperature_2m_min' => [6, 5],
                ],
            ]),
        ]);
    }

    private function fakeCurrency(): void
    {
        Http::fake([
            'api.frankfurter.dev/v1/currencies' => Http::response(['USD' => 'United States Dollar', 'EUR' => 'Euro']),
            'api.frankfurter.dev/v1/latest*' => Http::response(['date' => '2026-10-23', 'rates' => ['EUR' => 0.9]]),
        ]);
    }

    private function serviceDown(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
    }

    public function test_weather_shows_current_conditions_and_forecast(): void
    {
        $this->fakeWeather();

        $this->get('/weather?city=Paris')
            ->assertOk()
            ->assertSee('Paris, France')
            ->assertSee('9.2°C')
            ->assertSee('Overcast')
            ->assertSee('Light rain');
    }

    public function test_weather_uses_the_default_city(): void
    {
        $this->fakeWeather();

        $this->get('/weather')->assertOk();
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'name=London'));
    }

    public function test_weather_reports_an_unknown_city(): void
    {
        $this->fakeWeather();

        $this->get('/weather?city=Nowhere')->assertSee('No place found');
    }

    public function test_weather_reports_when_the_service_is_down(): void
    {
        $this->serviceDown();

        $this->get('/weather')->assertOk()->assertSee('did not respond');
    }

    public function test_news_lists_stories(): void
    {
        Http::fake(['hn.algolia.com/*' => Http::response(['hits' => [
            ['objectID' => '1', 'title' => 'With link', 'url' => 'https://example.com', 'points' => 10, 'num_comments' => 3, 'author' => 'ann'],
            ['objectID' => '2', 'title' => 'Ask HN', 'url' => null, 'points' => null, 'num_comments' => null, 'author' => 'bob'],
        ]])]);

        $response = $this->get('/news')->assertOk();

        $response->assertSee('href="https://example.com"', false);
        $this->assertSame('https://news.ycombinator.com/item?id=2', $response->viewData('stories')[1]['url']);
        $this->assertSame(0, $response->viewData('stories')[1]['comments']);
    }

    public function test_news_reports_when_the_service_is_down(): void
    {
        $this->serviceDown();

        $this->get('/news')->assertOk()->assertSee('did not respond');
    }

    public function test_currency_converts(): void
    {
        $this->fakeCurrency();

        $this->get('/currency?amount=100&from=USD&to=EUR')->assertSee('90.00 EUR');
    }

    public function test_currency_needs_no_api_call_for_the_same_currency(): void
    {
        $this->fakeCurrency();

        $this->get('/currency?amount=5&from=EUR&to=EUR')->assertSee('5.00 EUR');
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/latest'));
    }

    public function test_currency_rejects_a_bad_amount(): void
    {
        $this->fakeCurrency();

        $this->get('/currency?amount=abc&from=USD&to=EUR')->assertSee('Enter a number');
    }

    public function test_currency_rejects_an_unknown_currency(): void
    {
        $this->fakeCurrency();

        $this->get('/currency?amount=1&from=USD&to=XXX')->assertSee('Choose currencies from the list');
    }

    public function test_currency_reports_when_the_service_is_down(): void
    {
        $this->serviceDown();

        $this->get('/currency')->assertOk()->assertSee('did not respond');
    }
}
