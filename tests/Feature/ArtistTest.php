<?php

namespace Tests\Feature;

use App\Models\Artist;
use App\Services\MusicBrainz\ArtistLibrary;
use App\Services\MusicBrainz\ArtistProfile;
use App\Services\MusicBrainz\MusicBrainzClient;
use App\Services\MusicBrainz\Names;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArtistTest extends TestCase
{
    use RefreshDatabase;

    private const PEARL_JAM = '83b9cbe7-9857-49e2-ab8e-b57b01038103';

    private const VEDDER = '8b9d3d7f-3a37-4e8f-b9e8-0e3b6a4f1c2d';

    private const TAME_IMPALA = '22222222-2222-2222-2222-222222222222';

    private bool $musicBrainzDown = false;

    /** What the fake iHeart radio feed reports, for homepage tests. */
    private array $onAir = [];

    protected function setUp(): void
    {
        parent::setUp();

        // No waiting between requests in tests.
        $client = new MusicBrainzClient;
        $client->minInterval = $client->retryDelay = 0;
        $this->app->instance(MusicBrainzClient::class, $client);

        Http::fake(function (Request $request) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            if ($this->musicBrainzDown) {
                return Http::response(null, 503);
            }
            if ($path === '/ws/2/artist/') {
                $known = ['artist:"pearl jam"' => self::PEARL_JAM, 'artist:"tame impala"' => self::TAME_IMPALA];
                $id = $known[strtolower($request['query'])] ?? null;

                return Http::response(['artists' => $id
                    ? [['id' => $id, 'name' => preg_replace('/^artist:"(.*)"$/', '$1', $request['query']), 'score' => 100]]
                    : [['id' => 'x', 'name' => 'Something Else', 'score' => 95]]]);
            }
            if ($path === '/ws/2/release-group') {
                $groups = $request['artist'] === self::PEARL_JAM ? self::releaseGroups() : [];

                return Http::response(['release-groups' => $groups, 'release-group-count' => count($groups)]);
            }
            if (str_starts_with($path, '/ws/2/artist/')) {
                $id = basename($path);

                $name = $id === self::TAME_IMPALA ? 'Tame Impala' : 'Eddie Vedder';

                return Http::response($id === self::PEARL_JAM ? self::pearlJam() : ['id' => $id, 'name' => $name, 'relations' => []]);
            }

            if (str_contains($request->url(), 'us.api.iheart.com') && $this->onAir) {
                return Http::response(['data' => $this->onAir]);
            }

            return Http::response(null, 503); // the other radio feed on the homepage
        });
    }

    private static function pearlJam(): array
    {
        return [
            'id' => self::PEARL_JAM, 'name' => 'Pearl Jam', 'type' => 'Group', 'country' => 'US',
            'area' => ['name' => 'United States'], 'begin-area' => ['name' => 'Seattle'],
            'life-span' => ['begin' => '1990', 'end' => null, 'ended' => false],
            'aliases' => [['name' => 'Mookie Blaylock']], 'isnis' => ['0000000123426109'], 'ipis' => [],
            'genres' => [['name' => 'rock', 'count' => 5], ['name' => 'grunge', 'count' => 9]],
            'tags' => [['name' => 'grunge', 'count' => 9], ['name' => 'seattle', 'count' => 3]],
            'rating' => ['value' => 4.6, 'votes-count' => 29],
            'relations' => [
                ['target-type' => 'url', 'type' => 'official homepage', 'url' => ['resource' => 'https://pearljam.com/']],
                ['target-type' => 'url', 'type' => 'free streaming', 'url' => ['resource' => 'https://open.spotify.com/artist/1w5K']],
                ['target-type' => 'url', 'type' => 'image', 'url' => ['resource' => 'https://commons.wikimedia.org/wiki/File:PJ.jpg']],
                ['target-type' => 'artist', 'type' => 'member of band', 'direction' => 'backward', 'begin' => '1990', 'ended' => false,
                    'attributes' => ['original', 'lead vocals'], 'artist' => ['id' => self::VEDDER, 'name' => 'Eddie Vedder']],
                ['target-type' => 'artist', 'type' => 'member of band', 'direction' => 'backward', 'begin' => '1990', 'ended' => false,
                    'attributes' => ['guitar'], 'artist' => ['id' => self::VEDDER, 'name' => 'Eddie Vedder']],
                ['target-type' => 'artist', 'type' => 'member of band', 'direction' => 'backward', 'begin' => '1990-09-22',
                    'end' => '1991-05-22', 'ended' => true, 'attributes' => ['drums'],
                    'artist' => ['id' => '11111111-1111-1111-1111-111111111111', 'name' => 'Dave Krusen']],
            ],
        ];
    }

    private static function releaseGroups(): array
    {
        return [
            ['id' => 'aaaaaaaa-0000-0000-0000-000000000001', 'title' => 'Vs.', 'primary-type' => 'Album', 'first-release-date' => '1993-10-08'],
            ['id' => 'aaaaaaaa-0000-0000-0000-000000000002', 'title' => 'Ten', 'primary-type' => 'Album', 'first-release-date' => '1991-08-27'],
            ['id' => 'aaaaaaaa-0000-0000-0000-000000000003', 'title' => 'Alive', 'primary-type' => 'Single', 'first-release-date' => '1991'],
            ['id' => 'aaaaaaaa-0000-0000-0000-000000000004', 'title' => 'Live on Two Legs', 'primary-type' => 'Album',
                'secondary-types' => ['Live'], 'first-release-date' => '1998'],
        ];
    }

    private function musicBrainzRequests(): int
    {
        return Http::recorded(fn (Request $request) => str_contains($request->url(), 'musicbrainz.org'))->count();
    }

    public function test_names_are_compared_loosely_and_split(): void
    {
        $this->assertSame('pearl jam', Names::normalize('  PEARL   jam! '));
        $this->assertSame(['Tame Impala [+] Jennie', 'Tame Impala'], Names::candidates('Tame Impala [+] Jennie'));
        $this->assertSame(['Ninth Key, Ekoh & Lø Spirit', 'Ninth Key'], Names::candidates('Ninth Key, Ekoh & Lø Spirit'));
        $this->assertSame(['Pearl Jam'], Names::candidates('Pearl Jam'));
    }

    public function test_first_lookup_fetches_and_saves_everything(): void
    {
        $artist = app(ArtistLibrary::class)->find('Pearl Jam');

        $this->assertSame('Pearl Jam', $artist->name);
        $this->assertSame(4.6, Artist::sole()->data['rating']['value']);
        $this->assertCount(4, Artist::sole()->release_groups);
        $this->assertSame(3, $this->musicBrainzRequests()); // search, artist, release groups
        Http::assertSent(fn (Request $request) => $request->hasHeader('User-Agent', MusicBrainzClient::USER_AGENT));
    }

    public function test_second_lookup_uses_the_database_only(): void
    {
        $library = app(ArtistLibrary::class);
        $library->find('Pearl Jam');
        $library->find('  pearl jam ');

        $this->assertSame(3, $this->musicBrainzRequests());
    }

    public function test_combined_names_fall_back_to_the_first_artist(): void
    {
        $this->assertSame('Tame Impala', app(ArtistLibrary::class)->find('Tame Impala [+] Jennie')->name);
        Http::assertSent(fn (Request $request) => ($request['query'] ?? null) === 'artist:"Tame Impala"');
    }

    public function test_a_close_but_different_name_is_not_a_match_and_is_remembered(): void
    {
        $library = app(ArtistLibrary::class);
        $this->assertNull($library->find('Unknown Band'));
        $requests = $this->musicBrainzRequests();
        $this->assertNull($library->find('Unknown Band'));

        $this->assertSame($requests, $this->musicBrainzRequests());
    }

    public function test_old_data_is_refreshed(): void
    {
        $library = app(ArtistLibrary::class);
        $library->find('Pearl Jam');
        Artist::query()->update(['fetched_at' => now()->subDays(31)]);
        $library->find('Pearl Jam');

        $this->assertSame(2, Http::recorded(fn (Request $r) => str_contains($r->url(), '/ws/2/artist/'.self::PEARL_JAM))->count());
    }

    public function test_profile_groups_everything_for_the_page(): void
    {
        $profile = ArtistProfile::from(app(ArtistLibrary::class)->find('Pearl Jam'));

        $this->assertSame(['Group', 'Seattle, United States', '1990–present'], [$profile['type'], $profile['origin'], $profile['active']]);
        $this->assertSame(['grunge', 'rock'], $profile['genres']);
        $this->assertSame(['seattle'], $profile['tags']);
        $this->assertSame('https://commons.wikimedia.org/wiki/Special:FilePath/PJ.jpg?width=640', $profile['image']);
        $this->assertSame(['Eddie Vedder', 'Dave Krusen'], array_column($profile['members'], 'name'));
        $this->assertSame(['lead vocals', 'guitar'], $profile['members'][0]['roles']); // two entries, one card
        $this->assertSame('1990–1991', $profile['members'][1]['years']);
        $this->assertSame(
            ['Albums' => ['Ten', 'Vs.'], 'Singles' => ['Alive'], 'Live' => ['Live on Two Legs']],
            collect($profile['discography'])->mapWithKeys(fn ($s) => [$s['label'] => array_column($s['releases'], 'title')])->all(),
        );
        $this->assertSame(['Listen' => ['Spotify'], 'Official' => ['pearljam.com']],
            collect($profile['links'])->mapWithKeys(fn ($g) => [$g['label'] => array_column($g['items'], 'label')])->all());
        $this->assertSame('https://pearljam.com/', $profile['official_url']);
    }

    public function test_lookup_redirects_to_the_artist_page(): void
    {
        $this->get('/artists?name=Pearl+Jam')->assertRedirect(route('artists.show', self::PEARL_JAM));
    }

    public function test_artist_page_shows_the_details(): void
    {
        $this->get('/artists?name=Pearl+Jam');

        $this->get('/artists/'.self::PEARL_JAM)->assertOk()->assertSeeInOrder([
            'Pearl Jam', 'Seattle, United States', 'grunge', 'Official site', 'Eddie Vedder', 'lead vocals, guitar',
            'coverartarchive.org/release-group/aaaaaaaa-0000-0000-0000-000000000002', 'Ten', 'Mookie Blaylock', '0000000123426109',
        ], false);
    }

    public function test_member_pages_are_fetched_when_first_opened(): void
    {
        $this->get('/artists/'.self::VEDDER)->assertOk()->assertSee('Eddie Vedder');
        $this->assertDatabaseHas('artists', ['mbid' => self::VEDDER]);
    }

    public function test_unknown_name_shows_not_found(): void
    {
        $this->get('/artists?name=Unknown+Band')->assertNotFound()->assertSee('No artist found');
    }

    public function test_musicbrainz_down_shows_a_message_after_one_retry(): void
    {
        $this->musicBrainzDown = true;

        $this->get('/artists?name=Somebody+New')->assertStatus(503)->assertSee('MusicBrainz did not respond');
        $this->assertSame(2, $this->musicBrainzRequests());
    }

    public function test_pages_link_artists_and_include_the_loading_overlay(): void
    {
        $this->onAir = [['title' => 'Alive', 'artist' => 'Pearl Jam', 'startTime' => 1792815600, 'endTime' => 9999999999]];

        $this->get('/')
            ->assertSee(route('artists.lookup', ['name' => 'Pearl Jam']), false)
            ->assertSee('class="card-link"', false)
            ->assertSee('id="loading-overlay"', false);
    }
}
