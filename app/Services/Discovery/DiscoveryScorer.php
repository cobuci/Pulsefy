<?php

namespace App\Services\Discovery;

final class DiscoveryScorer
{
    private const int SUPPRESSION_WINDOW_DAYS = 14;

    private const float DISLIKE_PENALTY = 0.2;

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, true>  $penalizedArtists  lowercase artist names with active skips
     */
    public function score(array $candidate, array $penalizedArtists = []): int
    {
        $affinity = min(100.0, max(0.0, (float) ($candidate['artist_affinity'] ?? 0))) / 100.0;

        $artistName = mb_strtolower((string) ($candidate['artist_name'] ?? ''));
        if ($artistName !== '' && isset($penalizedArtists[$artistName])) {
            $affinity *= self::DISLIKE_PENALTY;
        }

        $daysAgo = $candidate['recent_play_days_ago'];
        $recency = $daysAgo !== null
            ? max(0.0, 1.0 - ((float) $daysAgo / self::SUPPRESSION_WINDOW_DAYS))
            : 0.0;

        return (int) round((($affinity * 0.70) + ($recency * 0.30)) * 100);
    }
}
