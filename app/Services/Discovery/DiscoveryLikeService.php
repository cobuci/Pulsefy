<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryLikedTrack;
use App\Models\TrackInteraction;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class DiscoveryLikeService
{
    /**
     * @param  array<string, mixed>  $trackData
     */
    public function like(User $user, array $trackData): void
    {
        DiscoveryLikedTrack::updateOrCreate(
            ['user_id' => $user->id, 'spotify_id' => $trackData['spotify_id']],
            [
                'name' => $trackData['name'],
                'artist_name' => $trackData['artist'],
                'album_name' => $trackData['album'],
                'image_url' => $trackData['album_art'] ?? null,
                'liked_at' => now(),
            ],
        );

        TrackInteraction::updateOrCreate(
            ['user_id' => $user->id, 'spotify_id' => $trackData['spotify_id'], 'type' => 'like'],
            ['interacted_at' => now(), 'expires_at' => null],
        );

        Cache::forget("discovery:{$user->id}:".now()->toDateString());
    }

    public function skip(User $user, string $spotifyId): void
    {
        TrackInteraction::updateOrCreate(
            ['user_id' => $user->id, 'spotify_id' => $spotifyId, 'type' => 'skip'],
            ['interacted_at' => now(), 'expires_at' => now()->addDays(14)],
        );

        Cache::forget("discovery:{$user->id}:".now()->toDateString());
    }
}
