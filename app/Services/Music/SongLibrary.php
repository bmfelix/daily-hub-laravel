<?php

namespace App\Services\Music;

use App\Models\ArtistName;
use App\Models\Song;
use App\Services\MusicBrainz\Names;

/**
 * Finds a song's previews once and saves them. Apple comes first, then Deezer.
 */
class SongLibrary
{
    private const REFRESH_AFTER_DAYS = 30; // songs with a preview

    private const RETRY_MISSING_AFTER_DAYS = 7; // songs without one

    public function __construct(private MusicClient $client) {}

    public function find(string $artist, string $title): Song
    {
        $song = Song::firstWhere('key', Names::songKey($artist, $title));
        if ($song && ! $this->stale($song)) {
            return $song;
        }

        return $this->lookUp($song ?? new Song(['artist_name' => trim($artist), 'title' => trim($title)]));
    }

    private function stale(Song $song): bool
    {
        $days = $song->hasPreview() ? self::REFRESH_AFTER_DAYS : self::RETRY_MISSING_AFTER_DAYS;

        return $song->checked_at === null || $song->checked_at->lte(now()->subDays($days));
    }

    private function lookUp(Song $song): Song
    {
        $apple = $this->client->appleSong($song->artist_name, $song->title) ?? [];
        $deezer = empty($apple['previewUrl']) ? ($this->client->deezerSong($song->artist_name, $song->title) ?? []) : [];

        $song->apple_preview_url = $apple['previewUrl'] ?? '';
        $song->apple_url = $apple['trackViewUrl'] ?? '';
        $song->deezer_track_id = $deezer['id'] ?? null;
        $song->deezer_url = $deezer['link'] ?? '';
        $song->album = $apple['collectionName'] ?? $deezer['album']['title'] ?? '';
        $song->artwork_url = str_replace('100x100bb', '250x250bb', $apple['artworkUrl100'] ?? '') ?: ($deezer['album']['cover_medium'] ?? '');
        $artist = ArtistName::firstWhere('key', Names::key($song->artist_name))?->artist_id;
        if ($artist) {
            $song->artist_id = $artist;
        }
        $song->checked_at = now();
        $song->save();

        return $song;
    }
}
