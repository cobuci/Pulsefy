<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\User;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HydrateAlbumPageDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $userId, public string $albumSpotifyId) {}

    public function handle(SpotifyArtistProvider $artists): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $profile = $artists->album($user, $this->albumSpotifyId);

        if (is_array($profile) && $profile !== []) {
            Album::query()->updateOrCreate(
                ['spotify_id' => $this->albumSpotifyId],
                [
                    'name' => (string) data_get($profile, 'name', ''),
                    'album_type' => data_get($profile, 'album_type'),
                    'release_date' => data_get($profile, 'release_date'),
                    'images' => is_array(data_get($profile, 'images')) ? data_get($profile, 'images') : null,
                    'total_tracks' => (int) data_get($profile, 'total_tracks', 0),
                    'metadata_synced_at' => now(),
                ],
            );
        }

        $artists->albumTracks($user, $this->albumSpotifyId);
    }
}
