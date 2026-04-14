<?php

namespace App\Services\Spotify;

use App\Models\SpotifyStat;
use App\Models\User;
use Closure;

class SpotifyDataService
{
    public function __construct(
        private readonly SpotifyTokenService $tokenService,
    ) {}

    public function topTracks(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_tracks', $timeRange, function () use ($user, $timeRange) {
            $token = $this->tokenService->ensureFreshToken($user);

            return (new SpotifyClient($token))
                ->topTracks($timeRange)
                ->throw()
                ->json('items', []);
        });
    }

    public function topArtists(User $user, string $timeRange = 'medium_term'): array
    {
        return $this->cached($user, 'top_artists', $timeRange, function () use ($user, $timeRange) {
            $token = $this->tokenService->ensureFreshToken($user);

            return (new SpotifyClient($token))
                ->topArtists($timeRange)
                ->throw()
                ->json('items', []);
        });
    }

    public function recentlyPlayed(User $user): array
    {
        return $this->cached($user, 'recently_played', 'none', function () use ($user) {
            $token = $this->tokenService->ensureFreshToken($user);

            return (new SpotifyClient($token))
                ->recentlyPlayed()
                ->throw()
                ->json('items', []);
        });
    }

    private function cached(User $user, string $type, string $timeRange, Closure $fetch): array
    {
        $stat = SpotifyStat::firstOrNew([
            'user_id' => $user->id,
            'type' => $type,
            'time_range' => $timeRange,
        ]);

        if (! $stat->exists || $stat->isExpired()) {
            $stat->payload = $fetch();
            $stat->fetched_at = now();
            $stat->expires_at = now()->addSeconds($this->ttl($type, $timeRange));
            $stat->save();
        }

        return $stat->payload;
    }

    private function ttl(string $type, string $timeRange): int
    {
        if ($type === 'recently_played') {
            return 15 * 60;
        }

        return match ($timeRange) {
            'long_term' => 72 * 3600,
            default => 24 * 3600,
        };
    }
}
