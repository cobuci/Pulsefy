<?php

namespace App\Jobs;

use App\Models\Artist;
use App\Models\User;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class HydrateArtistPageDataJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 180;

    public function __construct(public int $userId, public string $artistSpotifyId) {}

    public function handle(SpotifyArtistProvider $artists): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $profile = $artists->artist($user, $this->artistSpotifyId);

        if (is_array($profile) && $profile !== []) {
            Artist::query()->updateOrCreate(
                ['artist_id' => $this->artistSpotifyId],
                [
                    'artist_name' => (string) data_get($profile, 'name', ''),
                    'genres' => is_array(data_get($profile, 'genres')) ? data_get($profile, 'genres') : [],
                    'fetched_at' => now(),
                    'expires_at' => now()->addDays(7),
                ],
            );
        }

        $artists->topTracks($user, $this->artistSpotifyId);
        $artists->albums($user, $this->artistSpotifyId);
    }
}
