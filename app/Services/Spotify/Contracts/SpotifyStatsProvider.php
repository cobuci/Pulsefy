<?php

namespace App\Services\Spotify\Contracts;

use App\Models\User;

interface SpotifyStatsProvider
{
    public function topTracks(User $user, string $timeRange = 'medium_term'): array;

    public function topArtists(User $user, string $timeRange = 'medium_term'): array;

    public function recentlyPlayed(User $user): array;

    public function recentlyPlayedUnique(User $user): array;
}
