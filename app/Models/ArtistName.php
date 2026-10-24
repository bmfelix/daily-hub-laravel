<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A name as the radio spells it, and the artist it matched (or none).
 */
#[Fillable(['key', 'name', 'artist_id', 'checked_at'])]
class ArtistName extends Model
{
    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
