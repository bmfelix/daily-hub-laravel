<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Everything saved about one artist, so each service is asked only once.
 */
#[Fillable(['mbid', 'name', 'data', 'release_groups', 'fetched_at', 'wikipedia_title', 'wikipedia_url', 'wikipedia_summary', 'wikipedia_image', 'apple_albums', 'extras_checked_at'])]
class Artist extends Model
{
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'release_groups' => 'array',
            'apple_albums' => 'array',
            'fetched_at' => 'datetime',
            'extras_checked_at' => 'datetime',
        ];
    }

    public function names(): HasMany
    {
        return $this->hasMany(ArtistName::class);
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class)->orderBy('title');
    }
}
