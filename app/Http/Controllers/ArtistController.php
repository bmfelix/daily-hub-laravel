<?php

namespace App\Http\Controllers;

use App\Models\Artist;
use App\Services\MusicBrainz\ArtistLibrary;
use App\Services\MusicBrainz\ArtistProfile;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ArtistController extends Controller
{
    private const UNAVAILABLE = 'MusicBrainz did not respond. Try again shortly.';

    public function __construct(private ArtistLibrary $library) {}

    /**
     * Open the artist page for a name as the radio spells it.
     */
    public function lookup(Request $request): Response
    {
        $name = trim((string) $request->query('name'));
        abort_if($name === '', 404);

        try {
            $artist = $this->library->find($name);
        } catch (ConnectionException|RequestException) {
            return response()->view('artist-missing', ['error' => self::UNAVAILABLE], 503);
        }
        if (! $artist) {
            return response()->view('artist-missing', ['name' => $name], 404);
        }

        return redirect()->route('artists.show', $artist->mbid);
    }

    /**
     * One artist. Band members and related artists link here too, so fetch any not yet saved.
     */
    public function show(string $mbid): Response
    {
        $artist = Artist::firstWhere('mbid', $mbid);
        if (! $artist) {
            try {
                $artist = $this->library->save($mbid);
            } catch (RequestException $error) {
                abort_if(in_array($error->response->status(), [400, 404], true), 404);

                return response()->view('artist-missing', ['error' => self::UNAVAILABLE], 503);
            } catch (ConnectionException) {
                return response()->view('artist-missing', ['error' => self::UNAVAILABLE], 503);
            }
        }

        return response()->view('artist', ['artist' => ArtistProfile::from($artist)]);
    }
}
