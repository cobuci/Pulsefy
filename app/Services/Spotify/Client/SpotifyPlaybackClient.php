<?php

namespace App\Services\Spotify\Client;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SpotifyPlaybackClient
{
    private const BASE_URL = 'https://api.spotify.com/v1';

    public function __construct(private readonly string $accessToken) {}

    public function playbackState(): Response
    {
        return $this->get('/me/player', ['additional_types' => 'track']);
    }

    public function devices(): Response
    {
        return $this->get('/me/player/devices');
    }

    public function play(?array $uris = null, ?string $deviceId = null): Response
    {
        $payload = $uris !== null ? ['uris' => $uris] : [];
        $query = $deviceId !== null ? '?device_id='.urlencode($deviceId) : '';

        return $this->putJson('/me/player/play'.$query, $payload);
    }

    public function pause(): Response
    {
        return $this->putJson('/me/player/pause');
    }

    public function next(): Response
    {
        return $this->post('/me/player/next');
    }

    public function previous(): Response
    {
        return $this->post('/me/player/previous');
    }

    public function seek(int $positionMs): Response
    {
        return $this->put('/me/player/seek', ['position_ms' => $positionMs]);
    }

    public function setVolume(int $volumePercent): Response
    {
        return $this->put('/me/player/volume', ['volume_percent' => $volumePercent]);
    }

    public function shuffle(bool $state): Response
    {
        return $this->put('/me/player/shuffle', ['state' => $state ? 'true' : 'false']);
    }

    public function transferPlayback(string $deviceId, bool $play = true): Response
    {
        return $this->putJson('/me/player', [
            'device_ids' => [$deviceId],
            'play' => $play,
        ]);
    }

    private function get(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->get(self::BASE_URL.$path, $query);
    }

    private function post(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->post(self::BASE_URL.$path, $query);
    }

    private function put(string $path, array $query = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->put(self::BASE_URL.$path.'?'.http_build_query($query));
    }

    private function putJson(string $path, array $payload = []): Response
    {
        return Http::withToken($this->accessToken)
            ->timeout(10)
            ->connectTimeout(5)
            ->asJson()
            ->put(self::BASE_URL.$path, $payload);
    }
}
