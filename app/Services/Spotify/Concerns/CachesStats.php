<?php

namespace App\Services\Spotify\Concerns;

use App\Models\SpotifyStat;
use App\Models\User;
use Closure;

trait CachesStats
{
    private const RECENTLY_PLAYED_TTL = 15 * 60;

    private const ARTIST_PROFILE_TTL = 30 * 60;

    private const ARTIST_TOP_TRACKS_TTL = 30 * 60;

    private const ARTIST_ALBUMS_TTL = 6 * 3600;

    private const ALBUM_PROFILE_TTL = 6 * 3600;

    private const ALBUM_TRACKS_TTL = 6 * 3600;

    private const INSIGHTS_DASHBOARD_TTL = 30 * 60;

    private const INSIGHTS_ARTIST_TTL = 30 * 60;

    private const INSIGHTS_ALBUM_TTL = 30 * 60;

    private const DEFAULT_TTL = 24 * 3600;

    private const LONG_TERM_TTL = 72 * 3600;

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
            return self::RECENTLY_PLAYED_TTL;
        }

        if ($type === 'artist_profile') {
            return self::ARTIST_PROFILE_TTL;
        }

        if ($type === 'artist_top_tracks') {
            return self::ARTIST_TOP_TRACKS_TTL;
        }

        if ($type === 'artist_albums') {
            return self::ARTIST_ALBUMS_TTL;
        }

        if ($type === 'album_profile') {
            return self::ALBUM_PROFILE_TTL;
        }

        if ($type === 'album_tracks') {
            return self::ALBUM_TRACKS_TTL;
        }

        if ($type === 'insights_dashboard') {
            return self::INSIGHTS_DASHBOARD_TTL;
        }

        if ($type === 'insights_artist') {
            return self::INSIGHTS_ARTIST_TTL;
        }

        if ($type === 'insights_album') {
            return self::INSIGHTS_ALBUM_TTL;
        }

        return match ($timeRange) {
            'long_term' => self::LONG_TERM_TTL,
            default => self::DEFAULT_TTL,
        };
    }
}
