<?php

namespace App\Services\Spotify;

use App\Models\User;
use App\Services\Spotify\Client\SpotifyPlaybackClient;
use App\Services\Spotify\Client\SpotifyStatsClient;
use App\Services\Spotify\Concerns\CachesStats;
use Illuminate\Support\Facades\Log;

final readonly class SpotifyService
{
    use CachesStats;

    public function __construct(
        private SpotifyTokenService $tokenService,
    ) {}

    public function topTracks(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_tracks', $timeRange, function () use ($user, $timeRange) {
            try {
                $response = $this->statsClient($user)->topTracks($timeRange);

                if (in_array($response->status(), [401, 403])) {
                    return [];
                }

                return $response->throw()->json('items', []);
            } catch (\Throwable $e) {
                Log::warning('Spotify topTracks failed', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    public function topArtists(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_artists', $timeRange, function () use ($user, $timeRange) {
            try {
                $response = $this->statsClient($user)->topArtists($timeRange);

                if (in_array($response->status(), [401, 403])) {
                    return [];
                }

                return $response->throw()->json('items', []);
            } catch (\Throwable $e) {
                Log::warning('Spotify topArtists failed', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    public function recentlyPlayed(User $user): array
    {
        return $this->cached($user, 'recently_played', 'none', function () use ($user) {
            try {
                $response = $this->statsClient($user)->recentlyPlayed();

                if (in_array($response->status(), [401, 403])) {
                    return [];
                }

                return $response->throw()->json('items', []);
            } catch (\Throwable $e) {
                Log::warning('Spotify recentlyPlayed failed', ['error' => $e->getMessage()]);

                return [];
            }
        });
    }

    public function recentlyPlayedUnique(User $user): array
    {
        $seen = [];

        return array_values(
            array_filter($this->recentlyPlayed($user), function (array $item) use (&$seen) {
                $trackId = $item['track']['id'] ?? null;

                if ($trackId === null || isset($seen[$trackId])) {
                    return false;
                }

                $seen[$trackId] = true;

                return true;
            }),
        );
    }

    public function currentlyPlaying(User $user): ?array
    {
        try {
            $response = $this->playbackClient($user)->playbackState();

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
            $response = $this->playbackClient($user)->devices();

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
        try {
            $response = $this->playbackClient($user)->play();

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable $e) {
            Log::warning('Spotify resumePlay failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function play(User $user, string $uri): bool
    {
        try {
            $client = $this->playbackClient($user);
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
        try {
            $response = $this->playbackClient($user)->pause();

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable $e) {
            Log::warning('Spotify pause failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function next(User $user): bool
    {
        try {
            $response = $this->playbackClient($user)->next();

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable $e) {
            Log::warning('Spotify next failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function previous(User $user): bool
    {
        try {
            $response = $this->playbackClient($user)->previous();

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable $e) {
            Log::warning('Spotify previous failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function seek(User $user, int $positionMs): bool
    {
        try {
            $response = $this->playbackClient($user)->seek($positionMs);

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable $e) {
            Log::warning('Spotify seek failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function transferPlayback(User $user, string $deviceId, bool $play = true): bool
    {
        try {
            $response = $this->playbackClient($user)->transferPlayback($deviceId, $play);

            return in_array($response->status(), [200, 202, 204]);
        } catch (\Throwable $e) {
            Log::warning('Spotify transferPlayback failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function statsClient(User $user): SpotifyStatsClient
    {
        return new SpotifyStatsClient($this->tokenService->ensureFreshToken($user));
    }

    private function playbackClient(User $user): SpotifyPlaybackClient
    {
        return new SpotifyPlaybackClient($this->tokenService->ensureFreshToken($user));
    }
}
