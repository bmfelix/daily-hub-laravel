<?php

namespace Tests\Feature;

use App\Modules\WeatherModule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Http::fake() answers every API call, so these tests run without the internet.
 */
class ApiModulesTest extends TestCase
{
    private const NEWS_RSS = <<<'XML'
        <?xml version="1.0"?><rss version="2.0"><channel><title>Top stories</title>
        <item><title>Big story - BBC</title><link>https://news.google.com/a</link><pubDate>Tue, 10 Nov 2026 01:38:49 GMT</pubDate><source url="https://www.bbc.com">BBC</source></item>
        <item><title>Second - NPR</title><link>https://news.google.com/b</link><pubDate>Tue, 10 Nov 2026 01:00:00 GMT</pubDate><source url="https://www.npr.org">NPR</source></item>
        </channel></rss>
        XML;

    private function fakeWeather(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => fn (Request $request) => str_contains($request->url(), 'Nowhere')
                ? Http::response(['generationtime_ms' => 0.1])
                : Http::response(['results' => [['name' => 'Paris', 'country' => 'France', 'latitude' => 48.85, 'longitude' => 2.35]]]),
            'nominatim.openstreetmap.org/*' => fn (Request $request) => str_contains($request->url(), 'lat=0')
                ? Http::response(['error' => 'Unable to geocode'])
                : Http::response(['name' => 'Chicago', 'address' => [
                    'city' => 'Chicago', 'country' => 'United States', 'country_code' => 'us',
                ]]),
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
            ->assertSee('12 km/h')
            ->assertSee('Overcast')
            ->assertSee('Light rain');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'temperature_unit=celsius'));
    }

    public function test_weather_page_and_each_day_follow_the_sky(): void
    {
        $this->fakeWeather();

        $response = $this->get('/weather?city=Paris')->assertSee('class="theme-weather sky-cloudy"', false);

        $this->assertSame(['clear', 'rain'], array_column($response->viewData('days'), 'sky'));
        $this->assertSame(
            ['clear', 'cloudy', 'fog', 'rain', 'snow', 'storm'],
            array_map(WeatherModule::sky(...), [0, 3, 45, 63, 75, 95]),
        );
    }

    public function test_weather_uses_fahrenheit_and_mph_in_the_united_states(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => [[
                'name' => 'Chicago', 'country' => 'United States', 'country_code' => 'US',
                'latitude' => 41.85, 'longitude' => -87.65,
            ]]]),
            'api.open-meteo.com/*' => Http::response([
                'current' => ['temperature_2m' => 48.6, 'apparent_temperature' => 44, 'wind_speed_10m' => 7.5, 'weather_code' => 3],
                'daily' => [
                    'time' => ['2026-10-24'],
                    'weather_code' => [0],
                    'temperature_2m_max' => [57],
                    'temperature_2m_min' => [41],
                ],
            ]),
        ]);

        $this->get('/weather?city=Chicago')
            ->assertOk()
            ->assertSee('48.6°F')
            ->assertSee('7.5 mph')
            ->assertDontSee('°C')
            ->assertDontSee('km/h');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'temperature_unit=fahrenheit')
            && str_contains($request->url(), 'wind_speed_unit=mph'));
    }

    public function test_weather_uses_the_visitors_location(): void
    {
        $this->fakeWeather();

        $this->get('/weather?lat=41.85&lon=-87.65')
            ->assertOk()
            ->assertSee('Chicago, United States')
            ->assertDontSee('getCurrentPosition');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'temperature_unit=fahrenheit'));
    }

    public function test_weather_prefers_a_searched_city_over_the_visitors_location(): void
    {
        $this->fakeWeather();

        $this->get('/weather?city=Paris&lat=41.85&lon=-87.65')->assertSee('Paris, France');
    }

    public function test_weather_uses_the_default_city_for_invalid_coordinates(): void
    {
        $this->fakeWeather();

        $this->get('/weather?lat=north&lon=999')->assertOk();
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'name=London'));
    }

    public function test_weather_reports_no_place_at_the_visitors_location(): void
    {
        $this->fakeWeather();

        $this->get('/weather?lat=0&lon=-30')->assertSee('No town found');
    }

    public function test_weather_asks_for_a_location_only_when_no_city_is_chosen(): void
    {
        $this->fakeWeather();

        $this->get('/weather')->assertSee('getCurrentPosition');
        $this->get('/weather?city=Paris')->assertDontSee('getCurrentPosition');
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

    public function test_news_lists_stories_with_the_source_on_its_own_line(): void
    {
        Http::fake(['news.google.com/*' => Http::response(self::NEWS_RSS)]);

        $response = $this->get('/news')->assertOk();

        $response->assertSee('href="https://news.google.com/a"', false)->assertSee('>Big story</a>', false)->assertSee('BBC');
        $stories = $response->viewData('stories');
        $this->assertSame('Second', $stories[1]['title']);
        $this->assertSame('NPR', $stories[1]['source']);
        $this->assertSame(2026, $stories[0]['published']->year);
    }

    public function test_news_reports_a_broken_feed(): void
    {
        Http::fake(['news.google.com/*' => Http::response('not xml')]);

        $this->get('/news')->assertOk()->assertSee('did not respond');
    }

    public function test_news_reports_when_the_service_is_down(): void
    {
        $this->serviceDown();

        $this->get('/news')->assertOk()->assertSee('did not respond');
    }
}
