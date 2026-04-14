<?php

namespace App\Services\Spotify\Concerns;

use App\Models\SpotifyStat;
use App\Models\User;
use Closure;

trait CachesStats
{
    private const RECENTLY_PLAYED_TTL = 15 * 60;

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

        return match ($timeRange) {
            'long_term' => self::LONG_TERM_TTL,
            default => self::DEFAULT_TTL,
        };
    }
}
