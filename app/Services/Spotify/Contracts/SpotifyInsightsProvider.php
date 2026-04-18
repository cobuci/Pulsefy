<?php

namespace App\Services\Spotify\Contracts;

use App\Models\User;

interface SpotifyInsightsProvider
{
    public function dashboard(User $user, string $timeRange = 'medium_term'): array;

    public function artist(User $user, string $artistId): array;

    public function album(User $user, string $albumId): array;
}
