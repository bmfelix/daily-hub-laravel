<?php

namespace App\Services;

use App\Models\SpotifyAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The site owner's Spotify listening. Needs a Spotify developer app and one login, then renews itself.
 */
class SpotifyClient
{
    private const AUTHORIZE_URL = 'https://accounts.spotify.com/authorize';

    private const TOKEN_URL = 'https://accounts.spotify.com/api/token';

    private const API_URL = 'https://api.spotify.com/v1';

    private const SCOPES = 'user-read-currently-playing user-read-recently-played user-top-read';

    private const LISTENING_SECONDS = 20; // the homepage asks every 30 seconds, for every open page

    private const TOP_SECONDS = 3600;

    public static function isConfigured(): bool
    {
        return config('hub.spotify.client_id') && config('hub.spotify.client_secret');
    }

    /**
     * A Spotify player address for each playlist link in SPOTIFY_PLAYLIST_URL (comma separated).
     *
     * @return list<string>
     */
    public static function playlistEmbeds(): array
    {
        preg_match_all('/playlist[\/:]([A-Za-z0-9]+)/', (string) config('hub.spotify.playlist_url'), $matches);

        return array_map(fn ($id) => "https://open.spotify.com/embed/playlist/{$id}?theme=0", $matches[1]);
    }

    public function authorizeUrl(string $state): string
    {
        return self::AUTHORIZE_URL.'?'.http_build_query([
            'client_id' => config('hub.spotify.client_id'),
            'response_type' => 'code',
            'redirect_uri' => config('hub.spotify.redirect_uri'),
            'scope' => self::SCOPES,
            'state' => $state,
        ]);
    }

    public function connect(string $code): void
    {
        $tokens = $this->token(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => config('hub.spotify.redirect_uri')]);
        SpotifyAccount::query()->delete();
        $this->saveTokens(new SpotifyAccount, $tokens);
    }

    /**
     * What is playing now, or else what played last. Null if not connected.
     *
     * @return array<string, mixed>|null
     */
    public function listening(): ?array
    {
        $account = SpotifyAccount::first();
        if (! $account) {
            return null;
        }

        return Cache::remember('spotify-listening', self::LISTENING_SECONDS, function () use ($account) {
            $token = $this->freshToken($account);

            return $this->currentlyPlaying($token) ?? $this->lastPlayed($token);
        });
    }

    /**
     * Top tracks and artists for a period. Null if not connected.
     *
     * @return array{tracks: list<array<string, mixed>>, artists: list<array<string, mixed>>}|null
     */
    public function topItems(string $range): ?array
    {
        $account = SpotifyAccount::first();
        if (! $account) {
            return null;
        }

        return Cache::remember("spotify-top-{$range}", self::TOP_SECONDS, function () use ($account, $range) {
            $token = $this->freshToken($account);

            return [
                'tracks' => array_map(fn ($track) => self::trackDetails($track, false), $this->top($token, 'tracks', $range)),
                'artists' => array_map(self::artistDetails(...), $this->top($token, 'artists', $range)),
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function currentlyPlaying(string $token): ?array
    {
        $data = $this->get($token, 'me/player/currently-playing');
        if (! $data || empty($data['is_playing']) || empty($data['item'])) {
            return null;
        }

        return self::trackDetails($data['item'], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lastPlayed(string $token): ?array
    {
        $item = $this->get($token, 'me/player/recently-played', ['limit' => 1])['items'][0] ?? null;

        return $item ? self::trackDetails($item['track'], false, $item['played_at']) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function top(string $token, string $kind, string $range): array
    {
        return $this->get($token, "me/top/{$kind}", ['time_range' => $range, 'limit' => 10])['items'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $track
     * @return array<string, mixed>
     */
    private static function trackDetails(array $track, bool $playing, ?string $playedAt = null): array
    {
        $artists = array_column($track['artists'] ?? [], 'name');

        return [
            'title' => $track['name'],
            'artist' => implode(', ', $artists),
            'first_artist' => $artists[0] ?? '',
            'album' => $track['album']['name'] ?? '',
            'artwork' => $track['album']['images'][0]['url'] ?? '',
            'url' => $track['external_urls']['spotify'] ?? '',
            'playing' => $playing,
            'played_at' => $playedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $artist
     * @return array<string, mixed>
     */
    private static function artistDetails(array $artist): array
    {
        $images = $artist['images'] ?? [];

        return [
            'name' => $artist['name'],
            'image' => $images[count($images) - 2]['url'] ?? $images[0]['url'] ?? '',
            'genres' => array_slice($artist['genres'] ?? [], 0, 3),
            'url' => $artist['external_urls']['spotify'] ?? '',
        ];
    }

    private function freshToken(SpotifyAccount $account): string
    {
        if (now()->gte($account->expires_at)) {
            $this->saveTokens($account, $this->token(['grant_type' => 'refresh_token', 'refresh_token' => $account->refresh_token]));
        }

        return $account->access_token;
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private function saveTokens(SpotifyAccount $account, array $tokens): void
    {
        $account->access_token = $tokens['access_token'];
        // Spotify may or may not send a new refresh token; keep the old one if not.
        $account->refresh_token = $tokens['refresh_token'] ?? $account->refresh_token;
        $account->expires_at = Carbon::now()->addSeconds($tokens['expires_in'] - 60);
        $account->save();
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, mixed>
     */
    private function token(array $data): array
    {
        return Http::asForm()->timeout(8)
            ->withBasicAuth(config('hub.spotify.client_id'), config('hub.spotify.client_secret'))
            ->post(self::TOKEN_URL, $data)->throw()->json();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|null
     */
    private function get(string $token, string $path, array $params = []): ?array
    {
        $response = Http::withToken($token)->timeout(8)->get(self::API_URL."/{$path}", $params)->throw();

        return $response->status() === 204 ? null : $response->json();
    }
}
