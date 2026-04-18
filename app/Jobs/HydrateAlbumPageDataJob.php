<?php

namespace App\Jobs;

use App\Models\Album;
use App\Models\User;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Sync\SpotifyCatalogHydrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HydrateAlbumPageDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $userId, public string $albumSpotifyId) {}

    public function handle(SpotifyArtistProvider $artists, SpotifyCatalogHydrationService $catalog): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $profile = $artists->album($user, $this->albumSpotifyId);

        $album = null;

        if (is_array($profile) && $profile !== []) {
            $album = $catalog->hydrateAlbumProfile($profile);

            if ($album) {
                $catalog->hydrateAlbumArtists($album, $profile);
            }
        }

        if (! $album) {
            $album = Album::query()->where('spotify_id', $this->albumSpotifyId)->first();
        }

        $tracks = $artists->albumTracks($user, $this->albumSpotifyId);

        $catalog->hydrateTracks($tracks, $album);
    }
}
