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

    public function currentlyPlaying(): Response
    {
        return $this->get('/me/player/currently-playing', [
            'additional_types' => 'track',
        ]);
    }

    public function play(): Response
    {
        return $this->put('/me/player/play');
    }

    public function pause(): Response
    {
        return $this->put('/me/player/pause');
    }

    public function next(): Response
    {
        return $this->post('/me/player/next');
    }

    public function previous(): Response
    {
        return $this->post('/me/player/previous');
    }

    public function shuffle(bool $state): Response
    {
        return $this->put('/me/player/shuffle', ['state' => $state ? 'true' : 'false']);
    }

    private function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->retry(2, 500, fn ($e) => ! ($e instanceof RequestException && $e->response?->status() === 429))
            ->get(self::BASE_URL.$path, $query);
    }

    private function post(string $path): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->post(self::BASE_URL.$path);
    }

    private function put(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->put(self::BASE_URL.$path.'?'.http_build_query($query));
    }
}
