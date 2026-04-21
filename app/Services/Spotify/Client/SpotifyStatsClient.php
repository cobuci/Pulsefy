<?php

namespace App\Services\Spotify\Client;

use App\Services\Spotify\Support\GlobalSpotifyRateLimit;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SpotifyStatsClient
{
    private const string BASE_URL = 'https://api.spotify.com/v1';

    private const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly string $accessToken,
        private readonly ?GlobalSpotifyRateLimit $rateLimit = null,
    ) {}

    public function profile(): Response
    {
        return $this->get('/me');
    }

    public function topTracks(string $timeRange = 'medium_term', int $limit = 10): Response
    {
        return $this->get('/me/top/tracks', [
            'time_range' => $timeRange,
            'limit' => $limit,
        ]);
    }

    public function topTracksPage(
        string $timeRange = 'medium_term',
        int $limit = 50,
        int $offset = 0,
    ): Response {
        return $this->get('/me/top/tracks', [
            'time_range' => $timeRange,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function topArtists(string $timeRange = 'medium_term', int $limit = 10): Response
    {
        return $this->get('/me/top/artists', [
            'time_range' => $timeRange,
            'limit' => $limit,
        ]);
    }

    public function topArtistsPage(
        string $timeRange = 'medium_term',
        int $limit = 50,
        int $offset = 0,
    ): Response {
        return $this->get('/me/top/artists', [
            'time_range' => $timeRange,
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }

    public function recentlyPlayed(int $limit = 50): Response
    {
        return $this->get('/me/player/recently-played', [
            'limit' => $limit,
        ]);
    }

    /**
     * @param  array<int, string>  $artistIds
     */
    public function artists(array $artistIds): Response
    {
        return $this->get('/artists', [
            'ids' => implode(',', $artistIds),
        ]);
    }

    public function artist(string $artistId): Response
    {
        return $this->get('/artists/'.$artistId);
    }

    private function get(string $path, array $query = []): Response
    {
        $rateLimit = $this->rateLimit ?? app(GlobalSpotifyRateLimit::class);

        $lastResponse = Http::response(status: 429);

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $rateLimit->throttle();

            $response = Http::withToken($this->accessToken)
                ->timeout(10)
                ->connectTimeout(5)
                ->retry(2, 500, fn ($e) => ! ($e instanceof RequestException && $e->response->status() === 429), throw: false)
                ->get(self::BASE_URL.$path, $query);

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

        $baseDelayMs = 200;

        return min(5000, $baseDelayMs * (2 ** ($attempt - 1)));
    }
}
