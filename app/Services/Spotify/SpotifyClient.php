<?php

namespace App\Services\Spotify;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SpotifyClient
{
    private const BASE_URL = 'https://api.spotify.com/v1';

    public function __construct(private readonly string $accessToken) {}

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

    public function topArtists(string $timeRange = 'medium_term', int $limit = 10): Response
    {
        return $this->get('/me/top/artists', [
            'time_range' => $timeRange,
            'limit' => $limit,
        ]);
    }

    public function recentlyPlayed(int $limit = 20): Response
    {
        return $this->get('/me/player/recently-played', [
            'limit' => $limit,
        ]);
    }

    private function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 500, fn ($e) => ! ($e instanceof RequestException && $e->response->status() === 429))
            ->get(self::BASE_URL.$path, $query);
    }
}
