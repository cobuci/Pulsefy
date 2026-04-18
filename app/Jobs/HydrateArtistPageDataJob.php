<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Models\User;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Sync\SpotifyCatalogHydrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HydrateArtistPageDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $userId, public string $artistSpotifyId) {}

    public function handle(SpotifyArtistProvider $artists, SpotifyCatalogHydrationService $catalog): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $profile = $artists->artist($user, $this->artistSpotifyId);

        if (is_array($profile) && $profile !== []) {
            $catalog->hydrateArtistProfile($profile);
        }

        $topTracks = $artists->topTracks($user, $this->artistSpotifyId);
        $albums = $artists->albums($user, $this->artistSpotifyId);

        $artist = Artist::query()->where('artist_id', $this->artistSpotifyId)->first();

        $catalog->hydrateTracks($topTracks);

        if ($artist) {
            $catalog->hydrateArtistAlbums($artist, $albums);
        }
    }
}
