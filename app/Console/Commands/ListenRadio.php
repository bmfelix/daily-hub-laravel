<?php

namespace App\Console\Commands;

use App\Services\MusicBrainz\ArtistLibrary;
use App\Services\Radio\RadioListener;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('radio:listen {--interval=15 : Seconds between checks}')]
#[Description('Check the radio stations on a timer and broadcast song changes to open homepages.')]
class ListenRadio extends Command
{
    public function handle(RadioListener $listener, ArtistLibrary $library): void
    {
        $interval = (float) $this->option('interval');
        $announced = [];
        $this->info("Listening for song changes every {$interval} seconds.");

        while (true) {
            try {
                if ($listener->check()) {
                    $this->line("Broadcast: {$listener->lastSeen}");
                }
            } catch (Throwable $error) {
                // Keep listening through any one bad check.
                $this->error('Check failed: '.$error->getMessage());
            }
            // Look up new artists now, so their pages open instantly. Saved ones cost only a database read.
            foreach ($listener->currentArtists() as $name) {
                try {
                    $artist = $library->find($name);
                    if (! isset($announced[$name])) {
                        $announced[$name] = true;
                        $this->line("Artist ready: {$name} -> ".($artist->name ?? 'no match'));
                    }
                } catch (Throwable $error) {
                    $this->error("Artist lookup failed for {$name}: ".$error->getMessage());
                }
            }
            usleep((int) ($interval * 1_000_000));
        }
    }
}
