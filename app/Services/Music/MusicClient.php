<?php

namespace App\Services\Music;

use App\Services\MusicBrainz\Names;
use Illuminate\Support\Facades\Http;

/**
 * Song previews and album artwork from Apple and Deezer. Neither needs a key.
 */
class MusicClient
{
    private const ITUNES_SEARCH_URL = 'https://itunes.apple.com/search';

    private const DEEZER_SEARCH_URL = 'https://api.deezer.com/search';

    private const DEEZER_TRACK_URL = 'https://api.deezer.com/track/';

    /**
     * @return array<string, mixed>|null
     */
    public function appleSong(string $artist, string $title): ?array
    {
        $results = $this->get(self::ITUNES_SEARCH_URL, ['term' => "{$artist} {$title}", 'entity' => 'song', 'limit' => 10, 'country' => 'US'])['results'] ?? [];

        return self::bestMatch($results, $artist, $title, fn ($item) => [$item['artistName'] ?? '', $item['trackName'] ?? '']);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deezerSong(string $artist, string $title): ?array
    {
        $results = $this->get(self::DEEZER_SEARCH_URL, ['q' => "{$artist} {$title}", 'limit' => 10])['data'] ?? [];

        return self::bestMatch($results, $artist, $title, fn ($item) => [$item['artist']['name'] ?? '', $item['title'] ?? '']);
    }

    /**
     * A preview address. Deezer's expire within minutes, so they are fetched when played, never saved.
     */
    public function deezerPreview(int $trackId): string
    {
        return $this->get(self::DEEZER_TRACK_URL.$trackId)['preview'] ?? '';
    }

    /**
     * Every Apple album by this exact artist, keyed by album title.
     *
     * @return array<string, array{artwork: string, url: string}>
     */
    public function appleAlbums(string $artist): array
    {
        $results = $this->get(self::ITUNES_SEARCH_URL, ['term' => $artist, 'entity' => 'album', 'attribute' => 'artistTerm', 'limit' => 200, 'country' => 'US'])['results'] ?? [];
        $albums = [];
        foreach ($results as $item) {
            if (Names::compact($item['artistName'] ?? '') !== Names::compact($artist) || empty($item['artworkUrl100'])) {
                continue;
            }
            $albums[self::albumKey($item['collectionName'] ?? '')] ??= [
                'artwork' => str_replace('100x100bb', '250x250bb', $item['artworkUrl100']),
                'url' => $item['collectionViewUrl'] ?? '',
            ];
        }

        return $albums;
    }

    /**
     * An album's title without extras, so "Ten (Remastered)" and "Ten - EP" match "Ten".
     */
    public static function albumKey(string $title): string
    {
        return Names::normalize(preg_replace('/\s*(?:[(\[].*|-\s*(?:Single|EP))$/', '', $title));
    }

    /**
     * True when a service's artist name matches, allowing for "A & B" on either side.
     */
    public static function sameArtist(string $wanted, string $found): bool
    {
        $found = Names::compact($found);
        if ($found === '') {
            return false;
        }
        foreach (array_map(Names::compact(...), Names::candidates($wanted)) as $name) {
            if ($name !== '' && (str_contains($found, $name) || str_contains($name, $found))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The item for this song: an exact title first, then one with extra words like "(Remastered)".
     *
     * @param  list<array<string, mixed>>  $items
     * @param  callable(array<string, mixed>): array{string, string}  $read
     * @return array<string, mixed>|null
     */
    public static function bestMatch(array $items, string $artist, string $title, callable $read): ?array
    {
        $wanted = Names::compact($title);
        if ($wanted === '') {
            return null;
        }
        $matches = [];
        foreach ($items as $item) {
            [$foundArtist, $foundTitle] = $read($item);
            if (self::sameArtist($artist, $foundArtist)) {
                $matches[] = [Names::compact($foundTitle), $item];
            }
        }
        foreach ($matches as [$found, $item]) {
            if ($found === $wanted) {
                return $item;
            }
        }
        foreach ($matches as [$found, $item]) {
            if ($found !== '' && (str_starts_with($found, $wanted) || str_starts_with($wanted, $found))) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function get(string $url, array $params = []): array
    {
        return Http::timeout(8)->get($url, $params)->throw()->json() ?? [];
    }
}
