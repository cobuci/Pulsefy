<?php

namespace App\Services\Discovery;

final class DiscoveryScorer
{
    private const int SUPPRESSION_WINDOW_DAYS = 14;

    /**
     * @param  array<string, mixed>  $candidate
     */
    public function score(array $candidate): int
    {
        $a = min(100.0, max(0.0, (float) ($candidate['artist_affinity'] ?? 0))) / 100.0;
        $b = min(1.0, max(0.0, (float) ($candidate['lastfm_match'] ?? 0)));
        $c = min(1.0, max(0.0, (float) ($candidate['seed_track_match'] ?? 0)));

        $daysAgo = $candidate['recent_play_days_ago'];
        $d = $daysAgo !== null
            ? max(0.0, 1.0 - ((float) $daysAgo / self::SUPPRESSION_WINDOW_DAYS))
            : 0.0;

        return (int) round((($a * 0.50) + ($b * 0.30) + ($c * 0.10) + ($d * 0.10)) * 100);
    }
}
