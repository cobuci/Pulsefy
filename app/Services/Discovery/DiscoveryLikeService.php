<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryLikedTrack;
use App\Models\Track;
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
        $track = Track::query()->firstOrCreate(
            ['spotify_id' => $trackData['spotify_id']],
            [
                'name' => $trackData['name'],
                'image_url' => $trackData['album_art'] ?? null,
            ],
        );

        DiscoveryLikedTrack::updateOrCreate(
            ['user_id' => $user->id, 'track_id' => $track->id],
            ['liked_at' => now()],
        );

        TrackInteraction::updateOrCreate(
            ['user_id' => $user->id, 'track_id' => $track->id, 'type' => 'like'],
            ['interacted_at' => now(), 'expires_at' => null],
        );

        Cache::forget("discovery:{$user->id}:".now()->toDateString());
    }

    public function skip(User $user, string $spotifyId): void
    {
        $track = Track::query()->firstOrCreate(
            ['spotify_id' => $spotifyId],
            ['name' => ''],
        );

        TrackInteraction::updateOrCreate(
            ['user_id' => $user->id, 'track_id' => $track->id, 'type' => 'skip'],
            ['interacted_at' => now(), 'expires_at' => now()->addDays(14)],
        );

        Cache::forget("discovery:{$user->id}:".now()->toDateString());
    }
}
