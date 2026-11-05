<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One song heard on one station. The charts are counted from these.
 */
#[Fillable(['station', 'artist_name', 'title', 'song_key', 'played_at'])]
class Play extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['played_at' => 'datetime'];
    }
}
