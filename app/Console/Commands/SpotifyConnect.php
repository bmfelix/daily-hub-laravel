<?php

namespace App\Console\Commands;

use App\Services\SpotifyClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

#[Signature('spotify:connect')]
#[Description('Print a one-time link that connects the owner\'s Spotify account.')]
class SpotifyConnect extends Command
{
    public function handle(SpotifyClient $spotify): int
    {
        if (! SpotifyClient::isConfigured()) {
            $this->error('Add SPOTIFY_CLIENT_ID and SPOTIFY_CLIENT_SECRET to .env first.');

            return self::FAILURE;
        }

        $this->line('Open this link within 10 minutes:');
        $this->line($spotify->authorizeUrl(Crypt::encryptString((string) time())));

        return self::SUCCESS;
    }
}
