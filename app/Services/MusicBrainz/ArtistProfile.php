<?php

namespace App\Services\MusicBrainz;

use App\Models\Artist;

/**
 * Turns a saved artist record into what the artist page shows.
 */
class ArtistProfile
{
    private const LINK_GROUPS = [
        'Listen' => ['free streaming', 'streaming', 'youtube music', 'soundcloud', 'bandcamp'],
        'Official' => ['official homepage', 'blog'],
        'Social' => ['social network', 'youtube', 'myspace', 'fanpage', 'online community', 'video channel'],
        'Concerts' => ['setlistfm', 'songkick', 'bandsintown', 'ticketing'],
        'Buy' => ['purchase for download', 'purchase for mail-order'],
        'Reference' => ['wikidata', 'wikipedia', 'allmusic', 'discogs', 'last.fm', 'IMDb', 'BBC Music page', 'lyrics',
            'secondhandsongs', 'VIAF', 'other databases', 'discography page', 'purevolume'],
    ];

    private const SITE_NAMES = [
        'open.spotify.com' => 'Spotify', 'music.apple.com' => 'Apple Music', 'itunes.apple.com' => 'iTunes',
        'music.youtube.com' => 'YouTube Music', 'youtube.com' => 'YouTube', 'soundcloud.com' => 'SoundCloud',
        'deezer.com' => 'Deezer', 'tidal.com' => 'Tidal', 'music.amazon.com' => 'Amazon Music', 'napster.com' => 'Napster',
        'bandcamp.com' => 'Bandcamp', 'facebook.com' => 'Facebook', 'instagram.com' => 'Instagram', 'twitter.com' => 'Twitter',
        'x.com' => 'X', 'tiktok.com' => 'TikTok', 'wikidata.org' => 'Wikidata', 'wikipedia.org' => 'Wikipedia',
        'allmusic.com' => 'AllMusic', 'discogs.com' => 'Discogs', 'last.fm' => 'Last.fm', 'imdb.com' => 'IMDb',
        'bbc.co.uk' => 'BBC Music', 'genius.com' => 'Genius', 'setlist.fm' => 'Setlist.fm', 'songkick.com' => 'Songkick',
        'bandsintown.com' => 'Bandsintown', 'ticketmaster.com' => 'Ticketmaster', 'secondhandsongs.com' => 'SecondHandSongs',
        'viaf.org' => 'VIAF', 'myspace.com' => 'Myspace', 'play.google.com' => 'Google Play',
    ];

    private const RELEASE_SECTIONS = ['Albums', 'EPs', 'Singles', 'Live', 'Compilations', 'Other'];

    private const CAREER_ORDER = ['Albums', 'EPs']; // oldest first; the other sections show newest first

    private const SHOW_PER_SECTION = 60;

    /**
     * @return array<string, mixed>
     */
    public static function from(Artist $artist): array
    {
        $data = $artist->data;
        $life = $data['life-span'] ?? [];

        $links = [];
        $related = [];
        $members = [];
        $memberOf = [];
        $image = null;

        foreach ($data['relations'] ?? [] as $rel) {
            if (($rel['target-type'] ?? null) === 'url') {
                $url = $rel['url']['resource'];
                if ($rel['type'] === 'image') {
                    $image ??= self::commonsImage($url);

                    continue;
                }
                $group = 'Reference';
                foreach (self::LINK_GROUPS as $label => $types) {
                    if (in_array($rel['type'], $types, true)) {
                        $group = $label;
                        break;
                    }
                }
                // The same address can be listed under two kinds.
                $links[$group][$url] ??= ['url' => $url, 'label' => self::siteName($url), 'kind' => $rel['type']];
            } elseif (($rel['target-type'] ?? null) === 'artist') {
                $attributes = $rel['attributes'] ?? [];
                $ended = (bool) ($rel['ended'] ?? false);
                $person = [
                    'name' => $rel['artist']['name'],
                    'mbid' => $rel['artist']['id'],
                    'years' => self::span(self::year($rel['begin'] ?? null), self::year($rel['end'] ?? null) ?: ($ended ? '' : 'now')),
                    'roles' => array_values(array_diff($attributes, ['original'])),
                    'original' => in_array('original', $attributes, true),
                    'current' => ! $ended,
                ];
                if ($rel['type'] === 'member of band') {
                    if (($rel['direction'] ?? null) === 'backward') {
                        self::mergePerson($members, $person);
                    } else {
                        self::mergePerson($memberOf, $person);
                    }
                } else {
                    $related[ucfirst($rel['type'])][] = $person;
                }
            }
        }

        $sections = array_fill_keys(self::RELEASE_SECTIONS, []);
        foreach ($artist->release_groups as $group) {
            $sections[self::releaseSection($group)][] = [
                'title' => $group['title'],
                'year' => self::year($group['first-release-date'] ?? null),
                'mbid' => $group['id'],
                'cover' => "https://coverartarchive.org/release-group/{$group['id']}/front-250",
            ];
        }
        $discography = [];
        foreach ($sections as $label => $items) {
            if (! $items) {
                continue;
            }
            $careerOrder = in_array($label, self::CAREER_ORDER, true);
            usort($items, fn ($a, $b) => $careerOrder
                ? strcmp($a['year'] ?: '9999', $b['year'] ?: '9999')
                : strcmp($b['year'] ?: '0000', $a['year'] ?: '0000'));
            $discography[] = ['label' => $label, 'total' => count($items), 'releases' => array_slice($items, 0, self::SHOW_PER_SECTION)];
        }

        $byVotes = fn (array $items) => array_column(collect($items)->sortByDesc('count')->values()->all(), 'name');
        $genres = $byVotes($data['genres'] ?? []);
        $tags = array_values(array_diff($byVotes($data['tags'] ?? []), $genres));
        $aliases = array_values(array_unique(array_diff(array_column($data['aliases'] ?? [], 'name'), [$data['name']])));
        sort($aliases);
        usort($members, fn ($a, $b) => [! $a['current'], $a['years']] <=> [! $b['current'], $b['years']]);

        $listen = array_values($links['Listen'] ?? []);
        $official = collect($links['Official'] ?? [])->firstWhere('kind', 'official homepage');

        return [
            'name' => $data['name'],
            'mbid' => $artist->mbid,
            'disambiguation' => ($data['disambiguation'] ?? '') ?: null,
            'type' => $data['type'] ?? null,
            'gender' => $data['gender'] ?? null,
            'origin' => implode(', ', array_filter([$data['begin-area']['name'] ?? null, $data['area']['name'] ?? null])),
            'active' => self::span(self::year($life['begin'] ?? null), self::year($life['end'] ?? null) ?: (($life['ended'] ?? false) ? '' : 'present')),
            'ended' => (bool) ($life['ended'] ?? false),
            'rating' => $data['rating']['value'] ?? null,
            'rating_votes' => $data['rating']['votes-count'] ?? 0,
            'genres' => $genres,
            'tags' => array_slice($tags, 0, 12),
            'aliases' => $aliases,
            'image' => $image,
            'members' => $members,
            'member_of' => $memberOf,
            'related' => $related,
            'discography' => $discography,
            'release_count' => count($artist->release_groups),
            'links' => collect(array_keys(self::LINK_GROUPS))
                ->filter(fn ($label) => isset($links[$label]))
                ->map(fn ($label) => ['label' => $label, 'items' => array_values($links[$label])])
                ->values()->all(),
            // The header buttons: each listening site once, then the official site.
            'quick_links' => array_slice(array_values(collect($listen)->keyBy('label')->all()), 0, 4),
            'official_url' => $official['url'] ?? null,
            'isnis' => $data['isnis'] ?? [],
            'ipis' => $data['ipis'] ?? [],
            'musicbrainz_url' => "https://musicbrainz.org/artist/{$artist->mbid}",
            'fetched_at' => $artist->fetched_at,
        ];
    }

    public static function siteName(string $url): string
    {
        $host = preg_replace('/^www\./', '', parse_url($url, PHP_URL_HOST) ?? '');
        foreach (self::SITE_NAMES as $domain => $label) {
            if ($host === $domain || str_ends_with($host, ".{$domain}")) {
                return $label;
            }
        }

        return $host ?: $url;
    }

    /**
     * A direct image address for a Wikimedia Commons file page.
     */
    public static function commonsImage(string $url): ?string
    {
        return preg_match('#commons\.wikimedia\.org/wiki/File:(.+)$#', $url, $match)
            ? "https://commons.wikimedia.org/wiki/Special:FilePath/{$match[1]}?width=640"
            : null;
    }

    private static function year(?string $date): string
    {
        return substr($date ?? '', 0, 4);
    }

    private static function span(string $begin, string $end): string
    {
        return implode('–', array_filter([$begin, $end]));
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private static function releaseSection(array $group): string
    {
        $secondary = $group['secondary-types'] ?? [];
        if (in_array('Live', $secondary, true)) {
            return 'Live';
        }
        if (in_array('Compilation', $secondary, true)) {
            return 'Compilations';
        }
        if ($secondary) {
            return 'Other';
        }

        return ['Album' => 'Albums', 'EP' => 'EPs', 'Single' => 'Singles'][$group['primary-type'] ?? ''] ?? 'Other';
    }

    /**
     * MusicBrainz lists one entry per role or period; show each person once.
     *
     * @param  list<array<string, mixed>>  $people
     * @param  array<string, mixed>  $person
     */
    private static function mergePerson(array &$people, array $person): void
    {
        foreach ($people as &$existing) {
            if ($existing['mbid'] === $person['mbid']) {
                $existing['roles'] = array_values(array_unique([...$existing['roles'], ...$person['roles']]));
                if (! in_array($person['years'], explode(', ', $existing['years']), true)) {
                    $existing['years'] .= ", {$person['years']}";
                }
                $existing['original'] = $existing['original'] || $person['original'];
                $existing['current'] = $existing['current'] || $person['current'];

                return;
            }
        }
        $people[] = $person;
    }
}
