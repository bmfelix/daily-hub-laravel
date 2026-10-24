<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Everything MusicBrainz has on one artist, saved so it is fetched only once.
 */
#[Fillable(['mbid', 'name', 'data', 'release_groups', 'fetched_at'])]
class Artist extends Model
{
    protected function casts(): array
    {
        return ['data' => 'array', 'release_groups' => 'array', 'fetched_at' => 'datetime'];
    }

    public function names(): HasMany
    {
        return $this->hasMany(ArtistName::class);
    }
}
