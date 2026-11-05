<?php

namespace App\Models;

use App\Services\MusicBrainz\Names;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A song heard on the radio, with where to preview it. Each is looked up once.
 */
#[Fillable(['key', 'title', 'artist_name', 'artist_id', 'album', 'artwork_url', 'apple_preview_url', 'apple_url', 'deezer_track_id', 'deezer_url', 'checked_at'])]
class Song extends Model
{
    protected $attributes = [
        'album' => '', 'artwork_url' => '', 'apple_preview_url' => '', 'apple_url' => '', 'deezer_url' => '',
    ];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(fn (Song $song) => $song->key = Names::songKey($song->artist_name, $song->title));
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function hasPreview(): bool
    {
        return $this->apple_preview_url !== '' || $this->deezer_track_id !== null;
    }

    public function spotifyUrl(): string
    {
        return 'https://open.spotify.com/search/'.rawurlencode("{$this->artist_name} {$this->title}");
    }

    public function youtubeMusicUrl(): string
    {
        return 'https://music.youtube.com/search?q='.rawurlencode("{$this->artist_name} {$this->title}");
    }
}
