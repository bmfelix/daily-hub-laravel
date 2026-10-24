<?php

namespace Tests\Feature;

use App\Modules\RadioModule;
use App\Services\Radio\IHeartStation;
use App\Services\Radio\TritonStation;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RadioTest extends TestCase
{
    private const FUTURE = 9999999999;

    private const TRITON_XML = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?><nowplaying-info-list>
        <nowplaying-info type="track"><property name="cue_time_start"><![CDATA[2000000000000]]></property><property name="cue_time_duration"><![CDATA[200000]]></property><property name="cue_title"><![CDATA[Animal ]]></property><property name="track_artist_name"><![CDATA[Katseye]]></property><property name="track_id"><![CDATA[2]]></property></nowplaying-info>
        <nowplaying-info type="track"><property name="cue_time_start"><![CDATA[1000000300000]]></property><property name="cue_time_duration"><![CDATA[200000]]></property><property name="cue_title"><![CDATA[Umbrella]]></property><property name="track_artist_name"><![CDATA[Rihanna]]></property><property name="track_id"><![CDATA[1]]></property></nowplaying-info>
        <nowplaying-info type="track"><property name="cue_time_start"><![CDATA[1000000000000]]></property><property name="cue_time_duration"><![CDATA[200000]]></property><property name="cue_title"><![CDATA[Umbrella]]></property><property name="track_artist_name"><![CDATA[Rihanna]]></property><property name="track_id"><![CDATA[1]]></property></nowplaying-info>
        </nowplaying-info-list>
        XML;

    private function fakeIHeart(): void
    {
        Http::fake(['us.api.iheart.com/*' => Http::response(['data' => [
            ['title' => 'Self Esteem', 'artist' => 'The Offspring', 'startTime' => 1792815600, 'endTime' => self::FUTURE, 'imagePath' => 'http://image.iheart.com/a.jpg'],
            ['title' => 'Simple Man', 'artist' => 'Lynyrd Skynyrd', 'startTime' => 1792815000, 'endTime' => 1792815300],
        ]])]);
    }

    public function test_iheart_maps_history_and_uses_https_images(): void
    {
        $this->fakeIHeart();

        $tracks = (new IHeartStation('WEBN', '102.7 FM', 1701))->recent();

        $this->assertSame('Self Esteem', $tracks[0]['title']);
        $this->assertSame('https://image.iheart.com/a.jpg', $tracks[0]['image']);
        $this->assertSame([true, false], array_column($tracks, 'playing'));
    }

    public function test_triton_merges_repeated_cues_and_strips_titles(): void
    {
        Http::fake(['np.tritondigital.com/*' => Http::response(self::TRITON_XML)]);

        $tracks = (new TritonStation('WKRQ', '101.9 FM', 'WKRQFM'))->recent();

        $this->assertSame(['Animal', 'Umbrella'], array_column($tracks, 'title'));
        // The repeated Umbrella cue keeps the earlier start time.
        $this->assertEquals(1000000000, $tracks[1]['start']);
        $this->assertFalse($tracks[1]['playing']);
    }

    public function test_triton_rejects_invalid_xml(): void
    {
        $this->fakeIHeart();
        Http::fake(['np.tritondigital.com/*' => Http::response('not xml')]);

        $data = app(RadioModule::class)->card();

        $this->assertSame('Song information is not available right now.', $data['stations'][1]['error']);
    }

    public function test_home_card_shows_each_station_current_song(): void
    {
        $this->fakeIHeart();
        Http::fake(['np.tritondigital.com/*' => Http::response(null, 503)]);

        $this->get('/')
            ->assertSee('WEBN · Now playing')
            ->assertSee('Self Esteem')
            ->assertDontSee('Simple Man')
            ->assertSee('WKRQ · Unavailable')
            ->assertSee('Current conditions and a 5-day forecast');
    }

    public function test_page_lists_recent_songs_in_cincinnati_time(): void
    {
        $this->fakeIHeart();
        Http::fake(['np.tritondigital.com/*' => Http::response(self::TRITON_XML)]);

        $this->get('/radio')
            ->assertOk()
            ->assertSee('Simple Man')
            ->assertSee('12:10 AM')
            ->assertSee('Umbrella');
    }
}
