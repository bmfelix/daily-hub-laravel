<?php

namespace Tests\Feature;

use App\Events\SongChanged;
use App\Services\Radio\RadioListener;
use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveRadioTest extends TestCase
{
    private const FUTURE = 9999999999;

    /** The song the fake iHeart API reports; tests change it between checks. */
    private string $title = 'Self Esteem';

    private int $end = self::FUTURE;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'us.api.iheart.com/*' => fn () => Http::response(['data' => [
                ['title' => $this->title, 'artist' => 'The Offspring', 'startTime' => 1792815600, 'endTime' => $this->end],
            ]]),
            'np.tritondigital.com/*' => Http::response(null, 503),
        ]);
    }

    public function test_listener_broadcasts_only_when_a_song_changes(): void
    {
        Event::fake([SongChanged::class]);
        $listener = app(RadioListener::class);

        $this->assertTrue($listener->check()); // first check always broadcasts
        $this->assertFalse($listener->check()); // same song: nothing to send

        $this->title = 'Simple Man';
        $this->assertTrue($listener->check());

        Event::assertDispatchedTimes(SongChanged::class, 2);
        Event::assertDispatched(SongChanged::class, fn (SongChanged $event) => str_contains($event->html, 'Simple Man')
            && str_contains($event->html, 'data-live-card="radio"'));
    }

    public function test_listener_broadcasts_when_a_song_ends(): void
    {
        Event::fake([SongChanged::class]);
        $listener = app(RadioListener::class);
        $listener->check();

        $this->end = 0; // same song, now finished
        $this->assertTrue($listener->check());

        Event::assertDispatched(SongChanged::class, fn (SongChanged $event) => str_contains($event->html, 'Last played'));
    }

    public function test_listener_retries_after_a_failed_broadcast(): void
    {
        $calls = 0;
        Event::listen(SongChanged::class, function () use (&$calls) {
            if (++$calls === 1) {
                throw new \RuntimeException('Reverb is down');
            }
        });
        $listener = app(RadioListener::class);

        try {
            $listener->check();
            $this->fail('The first broadcast should fail.');
        } catch (\RuntimeException) {
        }

        $this->assertTrue($listener->check()); // same song, sent again
        $this->assertSame(2, $calls);
    }

    public function test_event_broadcasts_the_card_on_the_public_radio_channel(): void
    {
        $event = new SongChanged('<ul>card</ul>');

        $this->assertEquals(new Channel('radio'), $event->broadcastOn());
        $this->assertSame('song.changed', $event->broadcastAs());
        $this->assertSame(['html' => '<ul>card</ul>'], $event->broadcastWith());
    }

    public function test_home_page_connects_to_reverb(): void
    {
        $this->get('/')
            ->assertSee('laravel-echo@2.6.1', false)
            ->assertSee('data-live-card="radio"', false)
            ->assertSee('"host":"localhost"', false)
            ->assertSee('"port":8082', false);
    }
}
