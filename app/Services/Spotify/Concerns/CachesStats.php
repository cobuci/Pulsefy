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

    private const TOP_ITEMS_SNAPSHOT_TTL = 12 * 3600;

    private const DEFAULT_TTL = 24 * 3600;

    private const LONG_TERM_TTL = 72 * 3600;

    private function cached(User $user, string $type, string $timeRange, Closure $fetch): array
    {
        $stat = SpotifyStat::query()->where([
            'user_id' => $user->id,
            'type' => $type,
            'time_range' => $timeRange,
        ])->first();

        if ($stat !== null && ! $stat->isExpired()) {
            return $stat->payload;
        }

        $payload = $fetch();

        if ($payload === [] && ! $this->shouldPersistEmptyPayload($type)) {
            if ($stat !== null) {
                return $stat->payload;
            }

            return [];
        }

        $now = now();

        SpotifyStat::query()->upsert(
            [
                [
                    'user_id' => $user->id,
                    'type' => $type,
                    'time_range' => $timeRange,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'fetched_at' => $now,
                    'expires_at' => $now->copy()->addSeconds($this->ttl($type, $timeRange)),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ],
            ['user_id', 'type', 'time_range'],
            ['payload', 'fetched_at', 'expires_at', 'updated_at'],
        );

        return $payload;
    }

    private function shouldPersistEmptyPayload(string $type): bool
    {
        return ! in_array($type, [
            'artist_profile',
            'artist_top_tracks',
            'artist_albums',
            'album_profile',
            'album_tracks',
        ], true);
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

        if ($type === 'top_items_snapshot') {
            return self::TOP_ITEMS_SNAPSHOT_TTL;
        }

        return match ($timeRange) {
            'long_term' => self::LONG_TERM_TTL,
            default => self::DEFAULT_TTL,
        };
    }
}
