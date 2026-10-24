<?php

namespace App\Services\MusicBrainz;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * The MusicBrainz web service. No key, but at most one request per second.
 */
class MusicBrainzClient
{
    private const API_URL = 'https://musicbrainz.org/ws/2';

    // MusicBrainz asks every app to identify itself with a name and a contact address.
    public const USER_AGENT = 'DailyHub/1.0 ( bradfelix1@gmail.com )';

    private const ARTIST_INCLUDES = 'aliases+genres+tags+ratings+url-rels+artist-rels';

    /** Seconds between requests, with a little margin. */
    public float $minInterval = 1.1;

    /** Seconds before the one retry. */
    public float $retryDelay = 3;

    private static float $lastRequest = 0;

    /**
     * @return list<array<string, mixed>>
     */
    public function searchArtists(string $name, int $limit = 5): array
    {
        return $this->get('artist/', ['query' => "artist:\"{$name}\"", 'limit' => $limit])['artists'];
    }

    /**
     * @return array<string, mixed>
     */
    public function artist(string $mbid): array
    {
        return $this->get("artist/{$mbid}", ['inc' => self::ARTIST_INCLUDES]);
    }

    /**
     * Every album, EP, and single, up to $pages × 100 (big bands have over 1,000, mostly live).
     *
     * @return list<array<string, mixed>>
     */
    public function releaseGroups(string $mbid, int $pages = 15): array
    {
        $groups = [];
        for ($page = 0; $page < $pages; $page++) {
            $data = $this->get('release-group', ['artist' => $mbid, 'limit' => 100, 'offset' => $page * 100]);
            array_push($groups, ...$data['release-groups']);
            if (count($groups) >= $data['release-group-count']) {
                break;
            }
        }

        return $groups;
    }

    /**
     * One request, retried once if the connection fails or MusicBrainz says to slow down (503).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function get(string $path, array $params): array
    {
        foreach ([1, 2] as $attempt) {
            try {
                $response = $this->send($path, $params);
                if ($response->status() !== 503 || $attempt === 2) {
                    return $response->throw()->json();
                }
            } catch (ConnectionException $error) {
                if ($attempt === 2) {
                    throw $error;
                }
            }
            usleep((int) ($this->retryDelay * 1_000_000));
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function send(string $path, array $params): Response
    {
        $wait = self::$lastRequest + $this->minInterval - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        try {
            return Http::withUserAgent(self::USER_AGENT)
                ->timeout(10)
                ->get(self::API_URL."/{$path}", [...$params, 'fmt' => 'json']);
        } finally {
            self::$lastRequest = microtime(true);
        }
    }
}
