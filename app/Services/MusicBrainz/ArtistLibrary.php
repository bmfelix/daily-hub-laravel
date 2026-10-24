<?php

namespace App\Services\MusicBrainz;

use App\Models\Artist;
use App\Models\ArtistName;

/**
 * Finds artists by the name the radio uses. Checks the database before MusicBrainz.
 */
class ArtistLibrary
{
    private const REFRESH_AFTER_DAYS = 30; // saved artist details

    private const RETRY_UNMATCHED_AFTER_DAYS = 7; // names that matched no artist

    public function __construct(private MusicBrainzClient $client) {}

    /**
     * The saved Artist for this name, fetching it the first time. Null if nothing matches.
     */
    public function find(string $name): ?Artist
    {
        $key = Names::key($name);
        $entry = ArtistName::with('artist')->firstWhere('key', $key);
        if ($entry) {
            if ($entry->artist && $this->fresh($entry->artist)) {
                return $entry->artist;
            }
            if (! $entry->artist && $entry->checked_at->gt(now()->subDays(self::RETRY_UNMATCHED_AFTER_DAYS))) {
                return null;
            }
        }

        $mbid = $this->match($name);
        $artist = $mbid ? $this->save($mbid) : null;
        ArtistName::updateOrCreate(['key' => $key], ['name' => trim($name), 'artist_id' => $artist?->id, 'checked_at' => now()]);

        return $artist;
    }

    /**
     * The MusicBrainz ID whose name (or alias) really matches, or null.
     */
    public function match(string $name): ?string
    {
        foreach (Names::candidates($name) as $candidate) {
            $wanted = Names::normalize($candidate);
            foreach ($this->client->searchArtists($candidate) as $result) {
                if (($result['score'] ?? 0) < 90) {
                    break; // results are sorted by score
                }
                $names = [$result['name'], ...array_column($result['aliases'] ?? [], 'name')];
                if (in_array($wanted, array_map(Names::normalize(...), $names), true)) {
                    return $result['id'];
                }
            }
        }

        return null;
    }

    /**
     * Fetch and save one artist, unless the saved copy is still fresh.
     */
    public function save(string $mbid): Artist
    {
        $existing = Artist::firstWhere('mbid', $mbid);
        if ($existing && $this->fresh($existing)) {
            return $existing;
        }

        $data = $this->client->artist($mbid);
        $releaseGroups = $this->client->releaseGroups($mbid);

        return Artist::updateOrCreate(['mbid' => $mbid], [
            'name' => $data['name'],
            'data' => $data,
            'release_groups' => $releaseGroups,
            'fetched_at' => now(),
        ]);
    }

    private function fresh(Artist $artist): bool
    {
        return $artist->fetched_at->gt(now()->subDays(self::REFRESH_AFTER_DAYS));
    }
}
