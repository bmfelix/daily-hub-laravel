<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Models\ArtistName;
use App\Models\Note;
use App\Models\Play;
use App\Models\Song;
use App\Models\SpotifyAccount;
use App\Models\Task;
use App\Modules\BratModule;
use App\Modules\RadioModule;
use App\Services\CalendarClient;
use App\Services\Music\MusicClient;
use App\Services\Music\SongLibrary;
use App\Services\Music\WikipediaClient;
use App\Services\MusicBrainz\ArtistLibrary;
use App\Services\MusicBrainz\ArtistProfile;
use App\Services\MusicBrainz\MusicBrainzClient;
use App\Services\MusicBrainz\Names;
use App\Services\Radio\IHeartStation;
use App\Services\Radio\Station;
use App\Services\Radio\TritonStation;
use App\Services\SpotifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MusicFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private const MBID = '75af9d25-cf32-49c0-9192-60bd8e9784e6';

    private const SPOTIFY_TRACK = [
        'name' => '3AM', 'artists' => [['name' => 'Matchbox Twenty'], ['name' => 'Guest']],
        'album' => ['name' => 'Yourself or Someone Like You', 'images' => [['url' => 'https://i.scdn.co/big.jpg']]],
        'external_urls' => ['spotify' => 'https://open.spotify.com/track/1'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function artist(array $fields = []): Artist
    {
        return Artist::create([
            'mbid' => self::MBID, 'name' => 'Matchbox Twenty', 'data' => ['name' => 'Matchbox Twenty'], 'release_groups' => [],
            'fetched_at' => now(), 'extras_checked_at' => now(), ...$fields,
        ]);
    }

    private function play(string $station, string $artist, string $title, $when): Play
    {
        return Play::create(['station' => $station, 'artist_name' => $artist, 'title' => $title, 'song_key' => Names::songKey($artist, $title), 'played_at' => $when]);
    }

    private function usesStations(array $stations): void
    {
        $this->app->instance(RadioModule::class, new RadioModule($stations));
        $this->app->forgetInstance(\App\Modules\ModuleRegistry::class);
    }

    private function station(string $name = 'WEBN', array $tracks = []): Station
    {
        return new class($name, '1 FM', $tracks) extends Station
        {
            public function __construct(string $name, string $frequency, private array $list)
            {
                parent::__construct($name, $frequency);
            }

            public function streamUrl(): string
            {
                return 'https://stream.test/live';
            }

            protected function tracks(int $limit): array
            {
                return $this->list;
            }
        };
    }

    private function track(string $title = '3AM', string $artist = 'Matchbox 20'): array
    {
        return ['title' => $title, 'artist' => $artist, 'image' => '', 'start' => now()->subMinutes(5)->timestamp, 'end' => now()->addMinutes(5)->timestamp];
    }

    public function test_searches_aliases_as_well_as_names(): void
    {
        Http::fake(['musicbrainz.org/*' => Http::response(['artists' => []])]);
        $client = new MusicBrainzClient;
        $client->minInterval = 0;
        $client->searchArtists('Matchbox 20');

        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'artist:"Matchbox 20" OR alias:"Matchbox 20"'));
    }

    public function test_quotes_in_a_name_cannot_break_the_search(): void
    {
        Http::fake(['musicbrainz.org/*' => Http::response(['artists' => []])]);
        $client = new MusicBrainzClient;
        $client->minInterval = 0;
        $client->searchArtists('The "Weird" Band');

        Http::assertSent(fn (Request $request) => str_contains(urldecode($request->url()), 'artist:"The \\"Weird\\" Band"'));
    }

    public function test_saves_wikipedia_text_and_apple_artwork(): void
    {
        $client = $this->mock(MusicBrainzClient::class);
        $client->shouldReceive('artist')->andReturn(['id' => self::MBID, 'name' => 'Matchbox Twenty', 'relations' => []]);
        $client->shouldReceive('releaseGroups')->andReturn([]);
        Http::fake([
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => [[
                'title' => 'Matchbox Twenty', 'fullurl' => 'https://en.wikipedia.org/wiki/Matchbox_Twenty',
                'extract' => 'An American rock band.', 'description' => 'American rock band', 'thumbnail' => ['source' => 'https://upload.wikimedia.org/band.jpg'],
            ]]]]),
            'itunes.apple.com/*' => Http::response(['results' => [
                ['artistName' => 'Matchbox Twenty', 'collectionName' => 'Mad Season (Deluxe)', 'artworkUrl100' => 'https://a/100x100bb.jpg', 'collectionViewUrl' => 'https://music.apple.com/1'],
                ['artistName' => 'Tribute', 'collectionName' => 'Covers', 'artworkUrl100' => 'https://b/100x100bb.jpg'],
            ]]),
        ]);

        $artist = app(ArtistLibrary::class)->save(self::MBID);

        $this->assertSame('An American rock band.', $artist->wikipedia_summary);
        $this->assertSame(['mad season'], array_keys($artist->apple_albums));
        $this->assertSame('https://a/250x250bb.jpg', $artist->apple_albums['mad season']['artwork']);
        $this->assertSame('https://upload.wikimedia.org/band.jpg', ArtistProfile::from($artist)['image']);
    }

    public function test_a_wikipedia_failure_still_saves_the_artist(): void
    {
        $client = $this->mock(MusicBrainzClient::class);
        $client->shouldReceive('artist')->andReturn(['id' => self::MBID, 'name' => 'Matchbox Twenty', 'relations' => []]);
        $client->shouldReceive('releaseGroups')->andReturn([]);
        Http::fake(['*' => Http::response(null, 503)]);

        $artist = app(ArtistLibrary::class)->save(self::MBID);

        $this->assertSame('Matchbox Twenty', $artist->name);
        $this->assertNull($artist->extras_checked_at);
    }

    public function test_a_page_found_by_name_must_be_about_music(): void
    {
        Http::fake(['en.wikipedia.org/*' => Http::response(['query' => ['pages' => [['title' => 'Apple', 'fullurl' => 'u', 'extract' => 'A fruit.', 'description' => 'Edible fruit']]]])]);

        $this->assertNull((new WikipediaClient)->find(['name' => 'Apple', 'relations' => []]));
    }

    public function test_follows_the_wikidata_link(): void
    {
        Http::fake([
            'www.wikidata.org/*' => Http::response(['entities' => ['Q1' => ['sitelinks' => ['enwiki' => ['title' => 'Pearl Jam']]]]]),
            'en.wikipedia.org/*' => Http::response(['query' => ['pages' => [['title' => 'Pearl Jam', 'fullurl' => 'u', 'extract' => 'A band.']]]]),
        ]);

        $page = (new WikipediaClient)->find(['name' => 'Pearl Jam', 'relations' => [['url' => ['resource' => 'https://www.wikidata.org/wiki/Q1']]]]);
        $this->assertSame('Pearl Jam', $page['title']);
    }

    public function test_disambiguation_pages_are_skipped(): void
    {
        Http::fake(['en.wikipedia.org/*' => Http::response(['query' => ['pages' => [['title' => 'Bush', 'fullurl' => 'u', 'extract' => 'May refer to', 'pageprops' => ['disambiguation' => '']]]]])]);

        $this->assertNull((new WikipediaClient)->page('Bush'));
    }

    public function test_songs_use_apple_first(): void
    {
        Http::fake(['itunes.apple.com/*' => Http::response(['results' => [[
            'artistName' => 'Matchbox Twenty', 'trackName' => '3 am', 'collectionName' => 'Album', 'previewUrl' => 'https://a/p.m4a',
            'trackViewUrl' => 'https://music.apple.com/s', 'artworkUrl100' => 'https://a/100x100bb.jpg',
        ]]])]);

        $song = app(SongLibrary::class)->find('Matchbox Twenty', '3AM');

        $this->assertSame('https://a/p.m4a', $song->apple_preview_url);
        $this->assertSame('https://a/250x250bb.jpg', $song->artwork_url);
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'deezer'));
    }

    public function test_songs_fall_back_to_deezer(): void
    {
        Http::fake([
            'itunes.apple.com/*' => Http::response(['results' => []]),
            'api.deezer.com/*' => Http::response(['data' => [['id' => 42, 'title' => 'Enter Sandman', 'artist' => ['name' => 'Metallica'], 'link' => 'https://deezer.test/42', 'album' => ['title' => 'Metallica', 'cover_medium' => 'c.jpg']]]]),
        ]);

        $song = app(SongLibrary::class)->find('Metallica', 'Enter Sandman');

        $this->assertSame(42, (int) $song->deezer_track_id);
        $this->assertTrue($song->hasPreview());
    }

    public function test_a_song_is_saved_once_however_it_is_spelled(): void
    {
        Http::fake(['*' => Http::response(['results' => [], 'data' => []])]);
        $library = app(SongLibrary::class);

        $this->assertSame($library->find('Matchbox 20', '3AM')->id, $library->find('MATCHBOX 20', '3 AM')->id);
        Http::assertSentCount(2); // Apple and Deezer once, then saved
    }

    public function test_matching_rejects_another_artists_song(): void
    {
        $read = fn ($item) => $item;
        $this->assertNull(MusicClient::bestMatch([['Tribute Act', 'One']], 'U2', 'One', $read));
        $this->assertSame(['U2', 'One'], MusicClient::bestMatch([['U2', 'One (Live)'], ['U2', 'One']], 'U2', 'One', $read));
        $this->assertTrue(MusicClient::sameArtist('Tame Impala [+] Jennie', 'Tame Impala'));
    }

    public function test_preview_goes_to_apple(): void
    {
        $song = Song::create(['artist_name' => 'A', 'title' => 'B', 'apple_preview_url' => 'https://a/p.m4a']);

        $this->get("/songs/{$song->id}/preview")->assertRedirect('https://a/p.m4a');
    }

    public function test_preview_fetches_a_fresh_deezer_address(): void
    {
        $song = Song::create(['artist_name' => 'A', 'title' => 'B', 'deezer_track_id' => 42]);
        Http::fake(['api.deezer.com/track/42' => Http::response(['preview' => 'https://deezer.test/fresh.mp3'])]);

        $this->get("/songs/{$song->id}/preview")->assertRedirect('https://deezer.test/fresh.mp3');
    }

    public function test_preview_without_a_source_is_not_found(): void
    {
        $song = Song::create(['artist_name' => 'A', 'title' => 'B']);

        $this->get("/songs/{$song->id}/preview")->assertNotFound();
    }

    public function test_radio_page_shows_previews_and_links(): void
    {
        $song = Song::create(['artist_name' => 'Matchbox 20', 'title' => '3AM', 'apple_preview_url' => 'https://a/p.m4a', 'apple_url' => 'https://music.apple.com/s']);
        $this->usesStations([$this->station('WEBN', [$this->track()])]);

        $this->get('/radio')->assertOk()
            ->assertSee(route('songs.preview', $song), false)
            ->assertSee('https://music.apple.com/s', false)
            ->assertSee('open.spotify.com/search/Matchbox%2020%203AM', false)
            ->assertSee('music.youtube.com/search?q=Matchbox%2020%203AM', false)
            ->assertSee('data-src="https://stream.test/live"', false);
    }

    public function test_each_station_has_a_live_stream(): void
    {
        $this->assertSame('https://stream.revma.ihrhls.com/zc1701', (new IHeartStation('A', '1', 1701))->streamUrl());
        $this->assertStringContainsString('WKRQFM.mp3', (new TritonStation('B', '2', 'WKRQFM'))->streamUrl());
    }

    public function test_home_shows_each_station_as_its_own_card_with_saved_artwork(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        Song::create(['artist_name' => 'Matchbox 20', 'title' => '3AM', 'apple_preview_url' => 'https://a/p.m4a', 'artwork_url' => 'https://a/art.jpg']);
        $this->usesStations([$this->station('WEBN', [$this->track()]), $this->station('WKRQ', [])]);

        $response = $this->get('/')->assertOk()->assertSee('https://a/art.jpg', false)->assertSee('Listen live');
        $this->assertSame(2, substr_count($response->getContent(), 'class="card card-station"'));
    }

    public function test_home_shows_real_tasks_and_the_latest_note(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        Task::create(['title' => 'Buy milk']);
        Task::create(['title' => 'Finished', 'done' => true]);
        Note::create(['title' => 'Idea', 'body' => 'Some text']);

        $this->get('/')->assertSee('Buy milk')->assertDontSee('Finished')->assertSee('Idea');
    }

    public function test_outside_services_load_after_the_page(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        $response = $this->get('/');
        foreach (['weather', 'news', 'concerts', 'agenda'] as $slug) {
            $response->assertSee('data-card-url="'.url("/{$slug}").'?card=1"', false);
        }
    }

    public function test_a_module_can_return_just_its_card(): void
    {
        Http::fake(['news.google.com/*' => Http::response('<rss><channel><item><title>Big story - BBC</title><link>https://example.com</link><source>BBC</source></item></channel></rss>')]);

        $this->get('/news?card=1')->assertSee('Big story')->assertDontSee('<html', false);
    }

    public function test_pages_without_a_card_ignore_the_request_for_one(): void
    {
        $this->get('/todo?card=1')->assertSee('<html', false);
    }

    public function test_tasks_and_notes_cannot_be_changed_from_the_pages(): void
    {
        $this->post('/todo', ['action' => 'add', 'title' => 'Open'])->assertStatus(405);
        $this->post('/notes', ['action' => 'save', 'title' => 'Mine'])->assertStatus(405);

        $this->assertSame(0, Task::count());
        $this->assertSame(0, Note::count());
    }

    public function test_task_and_note_pages_have_no_forms(): void
    {
        Task::create(['title' => 'Buy milk']);
        $note = Note::create(['title' => 'Idea', 'body' => 'Some text']);

        $this->get('/todo')->assertSee('Buy milk')->assertDontSee('name="action"', false);
        $this->get("/notes?note={$note->id}")->assertSee('Some text')->assertDontSee('<textarea', false);
    }

    public function test_there_is_no_admin_area_or_login(): void
    {
        $this->get('/admin')->assertNotFound();
        $this->get('/login')->assertNotFound();
    }

    private function concertsFake(): void
    {
        config(['hub.ticketmaster_key' => 'key']);
        Http::fake(['app.ticketmaster.com/*' => function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['_embedded' => ['events' => [[
                'name' => "{$query['city']} show", 'url' => 'https://www.ticketmaster.com/e/1',
                'dates' => ['start' => ['localDate' => '2026-12-01', 'localTime' => '19:30:00']],
                'images' => [['url' => 'https://s1.ticketm.net/small.jpg', 'width' => 100], ['url' => 'https://s1.ticketm.net/big.jpg', 'width' => 640]],
                '_embedded' => ['venues' => [['name' => 'Heritage Bank Center']]],
            ]]]]);
        }]);
    }

    public function test_concerts_cover_all_three_cities(): void
    {
        $this->concertsFake();

        $this->get('/concerts')->assertSee('Cincinnati show')->assertSee('Dayton show')->assertSee('Columbus show')
            ->assertSee('Heritage Bank Center')->assertSee('https://s1.ticketm.net/big.jpg', false);
    }

    public function test_concerts_filter_by_city(): void
    {
        $this->concertsFake();

        $this->get('/concerts?city=Dayton')->assertSee('Dayton show')->assertDontSee('Columbus show');
    }

    public function test_concerts_are_remembered(): void
    {
        $this->concertsFake();
        $this->get('/concerts');
        $this->get('/concerts');

        Http::assertSentCount(3);
    }

    public function test_concerts_without_a_key_explain_themselves(): void
    {
        config(['hub.ticketmaster_key' => '']);

        $this->get('/concerts')->assertSee('Ticketmaster API key');
    }

    public function test_concerts_report_the_service_being_down(): void
    {
        config(['hub.ticketmaster_key' => 'key']);
        Http::fake(['*' => Http::response(null, 503)]);

        $this->get('/concerts')->assertSee('did not respond');
    }

    private function calendar(): string
    {
        $day = now()->format('Ymd');

        return str_replace("\n", "\r\n", <<<ICS
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Test//EN
        BEGIN:VEVENT
        UID:1
        DTSTAMP:20260101T000000Z
        DTSTART;VALUE=DATE:{$day}
        SUMMARY:Holiday
        END:VEVENT
        BEGIN:VEVENT
        UID:2
        DTSTAMP:20260101T000000Z
        DTSTART:{$day}T150000Z
        DTEND:{$day}T160000Z
        RRULE:FREQ=DAILY;COUNT=3
        SUMMARY:Standup
        LOCATION:Kitchen
        END:VEVENT
        END:VCALENDAR
        ICS);
    }

    public function test_agenda_shows_all_day_and_repeating_events(): void
    {
        config(['hub.calendar_urls' => ['webcal://p01-caldav.icloud.com/published/2/abc']]);
        Http::fake(['p01-caldav.icloud.com/*' => Http::response($this->calendar())]);

        $response = $this->get('/agenda')->assertOk()->assertSee('Holiday')->assertSee('All day')->assertSee('Kitchen');
        $this->assertSame(3, substr_count($response->getContent(), 'Standup'));
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://'));
    }

    public function test_agenda_reports_a_broken_calendar(): void
    {
        config(['hub.calendar_urls' => ['https://calendar.test/a.ics']]);
        Http::fake(['*' => Http::response(null, 404)]);

        $this->get('/agenda')->assertSee('could not be read');
    }

    public function test_agenda_without_calendars_explains_itself(): void
    {
        config(['hub.calendar_urls' => []]);

        $this->get('/agenda')->assertSee('Add your calendar links');
    }

    public function test_charts_count_plays_for_each_station(): void
    {
        $this->play('WEBN', 'Nirvana', 'Lithium', now()->subMinute());
        $this->play('WEBN', 'NIRVANA', 'LITHIUM', now()->subMinutes(2));
        $this->play('WEBN', 'Metallica', 'One', now()->subMinutes(3));
        $this->play('WKRQ', 'Ashe', 'Moral', now()->subMinutes(4));

        $stations = $this->get('/charts')->assertOk()->viewData('stations');

        $this->assertSame(['WEBN', 'WKRQ'], array_column($stations, 'name'));
        $this->assertSame(2, $stations[0]['songs'][0]['plays']);
        $this->assertSame(100, $stations[0]['songs'][0]['bar']);
        $this->assertSame(50, $stations[0]['songs'][1]['bar']);
    }

    public function test_chart_weeks_are_kept_apart(): void
    {
        $old = now()->subDays(14);
        $this->play('WEBN', 'Nirvana', 'Lithium', $old);

        $this->get('/charts')->assertDontSee('Lithium')->assertSee('No plays saved');
        $this->get('/charts?week='.$old->toDateString())->assertSee('Lithium')->assertSee('Later');
    }

    public function test_a_bad_week_falls_back_to_this_week(): void
    {
        $this->get('/charts?week=soon')->assertOk();
    }

    public function test_the_listener_saves_each_play_once(): void
    {
        $radio = new RadioModule([$this->station('WEBN', [$this->track()])]);

        $this->assertSame([['Matchbox 20', '3AM']], $radio->rememberRecent());
        $radio->rememberRecent();
        $this->assertSame(1, Play::count());
    }

    private function connected(): void
    {
        SpotifyAccount::create(['access_token' => 'token', 'refresh_token' => 'refresh', 'expires_at' => now()->addHour()]);
    }

    public function test_spotify_card_shows_what_is_playing(): void
    {
        $this->connected();
        Http::fake(['api.spotify.com/v1/me/player/currently-playing' => Http::response(['is_playing' => true, 'item' => self::SPOTIFY_TRACK])]);

        $this->get('/spotify/card')->assertSee('Playing now')->assertSee('Matchbox Twenty, Guest')->assertSee('Open in Spotify');
    }

    public function test_spotify_card_falls_back_to_the_last_song(): void
    {
        $this->connected();
        Http::fake([
            'api.spotify.com/v1/me/player/currently-playing' => Http::response(null, 204),
            'api.spotify.com/v1/me/player/recently-played*' => Http::response(['items' => [['track' => self::SPOTIFY_TRACK, 'played_at' => '2026-11-05T12:00:00.000Z']]]),
        ]);

        $this->get('/spotify/card')->assertSee('Last played');
    }

    public function test_paused_music_does_not_count_as_playing(): void
    {
        $this->connected();
        Http::fake([
            'api.spotify.com/v1/me/player/currently-playing' => Http::response(['is_playing' => false, 'item' => self::SPOTIFY_TRACK]),
            'api.spotify.com/v1/me/player/recently-played*' => Http::response(['items' => []]),
        ]);

        $this->get('/spotify/card')->assertDontSee('Playing now');
    }

    public function test_spotify_renews_an_expired_login_and_keeps_the_refresh_token(): void
    {
        SpotifyAccount::create(['access_token' => 'old', 'refresh_token' => 'refresh', 'expires_at' => now()->subMinute()]);
        config(['hub.spotify.client_id' => 'id', 'hub.spotify.client_secret' => 'secret']);
        Http::fake([
            'accounts.spotify.com/*' => Http::response(['access_token' => 'new', 'expires_in' => 3600]),
            'api.spotify.com/*' => Http::response(['items' => []]),
        ]);

        app(SpotifyClient::class)->listening();

        $account = SpotifyAccount::first();
        $this->assertSame('new', $account->access_token);
        $this->assertSame('refresh', $account->refresh_token);
    }

    public function test_spotify_tokens_are_encrypted_in_the_database(): void
    {
        $this->connected();

        $this->assertNotSame('token', \Illuminate\Support\Facades\DB::table('spotify_accounts')->value('access_token'));
    }

    public function test_spotify_callback_saves_the_login(): void
    {
        config(['hub.spotify.client_id' => 'id', 'hub.spotify.client_secret' => 'secret']);
        Http::fake(['accounts.spotify.com/*' => Http::response(['access_token' => 'a', 'refresh_token' => 'r', 'expires_in' => 3600])]);

        $this->get('/spotify/callback?'.http_build_query(['code' => 'c', 'state' => Crypt::encryptString((string) time())]))->assertRedirect('/');

        $this->assertSame('r', SpotifyAccount::first()->refresh_token);
    }

    public function test_spotify_callback_rejects_a_forged_request(): void
    {
        Http::fake();

        $this->get('/spotify/callback?code=c&state=forged')->assertStatus(400);
        Http::assertNothingSent();
        $this->assertSame(0, SpotifyAccount::count());
    }

    public function test_spotify_callback_rejects_an_old_link(): void
    {
        Http::fake();

        $this->get('/spotify/callback?'.http_build_query(['code' => 'c', 'state' => Crypt::encryptString((string) (time() - 3600))]))->assertStatus(400);
        $this->assertSame(0, SpotifyAccount::count());
    }

    public function test_playlists_get_buttons_only_when_there_are_several(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);
        config(['hub.spotify.playlist_url' => 'https://open.spotify.com/playlist/aaa?si=1']);
        $this->get('/')->assertSee('embed/playlist/aaa', false)->assertDontSee('data-playlist', false);

        config(['hub.spotify.playlist_url' => 'https://open.spotify.com/playlist/aaa?si=1,https://open.spotify.com/playlist/bbb?si=2']);
        $this->get('/')->assertSee('data-playlist="https://open.spotify.com/embed/playlist/bbb?theme=0"', false);
    }

    public function test_listening_page(): void
    {
        $this->connected();
        Http::fake([
            'api.spotify.com/v1/me/top/tracks*' => Http::response(['items' => [self::SPOTIFY_TRACK]]),
            'api.spotify.com/v1/me/top/artists*' => Http::response(['items' => [['name' => 'Matchbox Twenty', 'genres' => ['rock'], 'images' => [['url' => 'https://i.scdn.co/a.jpg']], 'external_urls' => ['spotify' => 'https://open.spotify.com/artist/1']]]]),
        ]);

        $this->get('/listening?range=long_term')->assertSee('3AM')->assertSee('Matchbox Twenty')->assertSee('All time');
        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'time_range=long_term'));
    }

    public function test_listening_not_connected(): void
    {
        $this->get('/listening')->assertSee('not connected');
    }

    public function test_about_skips_forks_and_survives_github_being_down(): void
    {
        Http::fake(['api.github.com/*' => Http::response([
            ['name' => 'mine', 'html_url' => 'https://github.com/x/mine', 'description' => 'My repo', 'language' => 'PHP', 'fork' => false],
            ['name' => 'forked', 'html_url' => 'https://github.com/x/forked', 'fork' => true],
        ])]);
        $this->get('/about')->assertSee('Brad Felix')->assertSee('Daily Hub')->assertSee('My repo')->assertDontSee('forked');

        Cache::flush();
        Http::fake(['*' => Http::response(null, 503)]);
    }

    public function test_built_page_headings_link_to_each_live_site(): void
    {
        config(['hub.sites' => ['Django' => 'https://one.test', 'Next.js' => 'https://two.test', 'Laravel' => '']]);

        $this->get('/built')
            ->assertSee('<a href="https://one.test">Django</a>', false)
            ->assertSee('<a href="https://two.test">Next.js</a>', false)
            ->assertSee('<th scope="col">Laravel</th>', false);
    }

    public function test_brat_page_describes_the_add_on_and_links_its_code(): void
    {
        $this->get('/brat')
            ->assertOk()
            ->assertSee('Rotation Assistant')
            ->assertSee('Learn from Blizzard')
            ->assertSee('https://github.com/bmfelix/BRAT');
    }

    public function test_brat_page_is_in_the_menu_not_on_the_homepage(): void
    {
        $this->get('/todo')->assertSee('href="'.url('/brat').'"', false);
        $this->assertFalse((new BratModule)->onHome);
    }

    public function test_built_page_is_in_the_footer_not_the_menu(): void
    {
        Http::fake(['*' => Http::response(null, 503)]);

        $this->get('/built')->assertSee('Payload CMS')->assertSee('Reverb')->assertSee('daily-hub-django');
        $this->get('/todo')->assertSee('href="'.url('/built').'">How it', false);
    }
}
