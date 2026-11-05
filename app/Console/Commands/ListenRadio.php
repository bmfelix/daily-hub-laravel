<?php

namespace App\Console\Commands;

use App\Modules\RadioModule;
use App\Services\Music\SongLibrary;
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
    private const SONGS_PER_CHECK = 3; // Apple allows about 20 requests a minute

    public function handle(RadioListener $listener, ArtistLibrary $library, SongLibrary $songs, RadioModule $radio): void
    {
        $waiting = [];
        $interval = (float) $this->option('interval');
        $announced = [];
        $this->info("Listening for song changes every {$interval} seconds.");

        while (true) {
            try {
                if ($listener->check()) {
                    $this->line("Broadcast: {$listener->lastSeen}");
                    foreach ($radio->rememberRecent() as [$artist, $title]) {
                        $waiting["{$artist}|{$title}"] = [$artist, $title];
                    }
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
            foreach (array_splice($waiting, 0, self::SONGS_PER_CHECK) as [$artist, $title]) {
                try {
                    $songs->find($artist, $title);
                } catch (Throwable $error) {
                    $this->error("Song lookup failed for {$artist} {$title}: ".$error->getMessage());
                }
            }
            usleep((int) ($interval * 1_000_000));
        }
    }
}
