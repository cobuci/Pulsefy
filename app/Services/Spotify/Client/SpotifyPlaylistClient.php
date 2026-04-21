<?php

namespace App\Services\Spotify\Client;

use App\Services\Spotify\Support\GlobalSpotifyRateLimit;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class SpotifyPlaylistClient
{
    private const string BASE_URL = 'https://api.spotify.com/v1';

    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $accessToken,
        private readonly ?GlobalSpotifyRateLimit $rateLimit = null,
    ) {}

    public function savedTracks(int $limit = 50, int $offset = 0): Response
    {
        return $this->get('/me/tracks', [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function currentUserPlaylists(int $limit = 20, int $offset = 0): Response
    {
        return $this->get('/me/playlists', [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function playlist(string $playlistId, ?string $market = 'from_token'): Response
    {
        return $this->get('/playlists/'.$playlistId, $this->marketQuery($market));
    }

    public function playlistTracks(string $playlistId, int $limit = 50, int $offset = 0, ?string $market = 'from_token'): Response
    {
        return $this->get('/playlists/'.$playlistId.'/items', [
            'limit' => $limit,
            'offset' => $offset,
            ...$this->marketQuery($market),
        ]);
    }

    private function marketQuery(?string $market): array
    {
        if (! is_string($market) || trim($market) === '') {
            return [];
        }

        return ['market' => $market];
    }

    private function get(string $path, array $query = []): Response
    {
        return $this->request('GET', $path, ['query' => $query]);
    }

    private function request(string $method, string $path, array $options = []): Response
    {
        $rateLimit = $this->rateLimit ?? app(GlobalSpotifyRateLimit::class);
        $lastResponse = Http::response(status: 429);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $rateLimit->throttle();

            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->connectTimeout(5)
                ->retry(2, 500, fn ($e) => ! ($e instanceof RequestException && $e->response?->status() === 429), throw: false)
                ->send($method, self::BASE_URL.$path, $options);

            $lastResponse = $response;

            if ($response->status() !== 429) {
                return $response;
            }

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep($this->retryAfterMilliseconds($response, $attempt) * 1000);
            }
        }

        return $lastResponse;
    }

    private function retryAfterMilliseconds(Response $response, int $attempt): int
    {
        $retryAfter = $response->header('Retry-After');

        if (is_string($retryAfter) && is_numeric($retryAfter)) {
            return max(0, min(5000, ((int) $retryAfter) * 1000));
        }

        return min(5000, 200 * (2 ** ($attempt - 1)));
    }
}
