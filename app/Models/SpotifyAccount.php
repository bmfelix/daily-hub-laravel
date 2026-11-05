<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

/**
 * The site owner's Spotify login. There is only ever one.
 */
#[Fillable(['access_token', 'refresh_token', 'expires_at'])]
#[Hidden(['access_token', 'refresh_token'])]
class SpotifyAccount extends Model
{
    protected function casts(): array
    {
        return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime'];
    }
}
