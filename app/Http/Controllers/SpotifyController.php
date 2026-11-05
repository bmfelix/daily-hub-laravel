<?php

namespace App\Http\Controllers;

use App\Services\SpotifyClient;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class SpotifyController extends Controller
{
    private const STATE_SECONDS = 600;

    public function __construct(private SpotifyClient $spotify) {}

    /**
     * What the homepage's Spotify panel shows. The page asks for this after it opens.
     */
    public function card(): View
    {
        try {
            return view('cards.spotify-live', ['track' => $this->spotify->listening(), 'configured' => SpotifyClient::isConfigured()]);
        } catch (ConnectionException|RequestException) {
            return view('cards.spotify-live', ['track' => null, 'configured' => true, 'error' => 'Spotify did not respond. Try again shortly.']);
        }
    }

    /**
     * Spotify returns here. The encrypted state shows this began with "php artisan spotify:connect".
     */
    public function callback(Request $request): Response
    {
        try {
            $started = (int) Crypt::decryptString((string) $request->query('state'));
        } catch (DecryptException) {
            return response('This Spotify link has expired. Run php artisan spotify:connect again.', 400);
        }
        if (time() - $started > self::STATE_SECONDS) {
            return response('This Spotify link has expired. Run php artisan spotify:connect again.', 400);
        }
        if (! $request->query('code')) {
            return redirect()->route('home'); // access was refused
        }

        try {
            $this->spotify->connect($request->query('code'));
        } catch (ConnectionException|RequestException) {
            return response('Spotify did not respond. Try connecting again.', 503);
        }

        return redirect()->route('home');
    }
}
