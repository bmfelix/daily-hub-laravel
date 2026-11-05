<?php

namespace App\Http\Controllers;

use App\Models\Song;
use App\Services\Music\MusicClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Symfony\Component\HttpFoundation\Response;

class SongController extends Controller
{
    /**
     * Send the audio player to a preview. Deezer's addresses expire, so each is fetched as it is played.
     */
    public function preview(Song $song, MusicClient $music): Response
    {
        if ($song->apple_preview_url !== '') {
            return redirect()->away($song->apple_preview_url);
        }
        abort_if($song->deezer_track_id === null, 404);

        try {
            $url = $music->deezerPreview($song->deezer_track_id);
        } catch (ConnectionException|RequestException) {
            return response('The preview service did not respond.', 503);
        }
        abort_if($url === '', 404);

        return redirect()->away($url);
    }
}
