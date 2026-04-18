<?php

namespace App\Services\Spotify\Playback;

use App\Models\User;
use App\Services\Spotify\Client\SpotifyPlaybackClient;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Log;

final readonly class SpotifyPlaybackService implements SpotifyPlaybackProvider
{
    public function __construct(
        private SpotifyTokenService $tokenService,
    ) {}

    public function currentlyPlaying(User $user): ?array
    {
        try {
            $response = $this->client($user)->playbackState();

            if (in_array($response->status(), [204, 401, 403]) || $response->body() === '') {
                return null;
            }

            $response->throw();

            $data = $response->json();

            if (empty($data['item']) || ($data['currently_playing_type'] ?? 'track') !== 'track') {
                return null;
            }

            return [
                'is_playing' => $data['is_playing'] ?? false,
                'shuffle_state' => $data['shuffle_state'] ?? false,
                'progress_ms' => $data['progress_ms'] ?? 0,
                'volume_percent' => $data['device']['volume_percent'] ?? null,
                'track' => $data['item'],
            ];
        } catch (\Throwable $e) {
            Log::warning('Spotify currentlyPlaying failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public function devices(User $user): array
    {
        try {
            $response = $this->client($user)->devices();

            if (in_array($response->status(), [401, 403])) {
                return [];
            }

            return $response->throw()->json('devices', []);
        } catch (\Throwable $e) {
            Log::warning('Spotify devices failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function resumePlay(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->play(), 'resumePlay');
    }

    public function play(User $user, string $uri): bool
    {
        try {
            $client = $this->client($user);
            $response = $client->play([$uri]);

            if (in_array($response->status(), [200, 202, 204])) {
                return true;
            }

            if ($response->status() === 404) {
                $deviceId = collect($client->devices()->json('devices', []))
                    ->whereNotNull('id')
                    ->where('is_restricted', false)
                    ->value('id');

                if ($deviceId) {
                    $retry = $client->play([$uri], $deviceId);

                    return in_array($retry->status(), [200, 202, 204]);
                }
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('Spotify play failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function pause(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->pause(), 'pause');
    }

    public function next(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->next(), 'next');
    }

    public function previous(User $user): bool
    {
        return $this->statusOk(fn () => $this->client($user)->previous(), 'previous');
    }

    public function seek(User $user, int $positionMs): bool
    {
        return $this->statusOk(fn () => $this->client($user)->seek($positionMs), 'seek');
    }

    public function setVolume(User $user, int $volumePercent): bool
    {
        return $this->statusOk(fn () => $this->client($user)->setVolume($volumePercent), 'setVolume');
    }

    public function transferPlayback(User $user, string $deviceId, bool $play = true): bool
    {
        return $this->statusOk(fn () => $this->client($user)->transferPlayback($deviceId, $play), 'transferPlayback');
    }

    private function statusOk(\Closure $callback, string $operation): bool
    {
        try {
            $response = $callback();

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable $e) {
            Log::warning("Spotify {$operation} failed", ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function client(User $user): SpotifyPlaybackClient
    {
        return new SpotifyPlaybackClient($this->tokenService->ensureFreshToken($user));
    }
}
