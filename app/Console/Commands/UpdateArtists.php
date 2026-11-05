<?php

namespace App\Console\Commands;

use App\Models\Artist;
use App\Services\MusicBrainz\ArtistLibrary;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('artists:update {--delay=3.5 : Seconds between artists. Apple allows about 20 requests a minute.}')]
#[Description('Add Wikipedia text and Apple artwork to saved artists that do not have them yet.')]
class UpdateArtists extends Command
{
    public function handle(ArtistLibrary $library): void
    {
        foreach (Artist::whereNull('extras_checked_at')->get() as $artist) {
            $library->addExtras($artist);
            $status = $artist->extras_checked_at ? 'done' : 'will try again later';
            $this->line("{$artist->name}: {$status}, ".($artist->wikipedia_summary ? 'Wikipedia' : 'no Wikipedia'));
            usleep((int) ((float) $this->option('delay') * 1_000_000));
        }
    }
}
