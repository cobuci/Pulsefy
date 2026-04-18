<?php

namespace App\Services\Spotify\Insights;

use App\Models\User;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use App\Services\Spotify\Contracts\SpotifyInsightsProvider;
use App\Services\Spotify\Contracts\SpotifyStatsProvider;

final readonly class SpotifyInsightsRefreshService
{
    public function __construct(
        private SpotifyStatsProvider $stats,
        private SpotifyArtistProvider $artists,
        private SpotifyInsightsProvider $insights,
    ) {}

    public function refreshForUser(User $user): void
    {
        $user->spotifyStats()->delete();

        foreach (['short_term', 'medium_term', 'long_term'] as $timeRange) {
            $this->stats->topTracks($user, $timeRange);
            $this->stats->topArtists($user, $timeRange);
            $this->insights->dashboard($user, $timeRange);
        }

        $recent = $this->stats->recentlyPlayed($user);

        $firstArtistId = data_get($recent, '0.track.artists.0.id');
        $firstAlbumId = data_get($recent, '0.track.album.id');

        if (is_string($firstArtistId) && $firstArtistId !== '') {
            $this->artists->artist($user, $firstArtistId);
            $this->artists->topTracks($user, $firstArtistId);
            $this->artists->albums($user, $firstArtistId);
            $this->insights->artist($user, $firstArtistId);
        }

        if (is_string($firstAlbumId) && $firstAlbumId !== '') {
            $this->artists->album($user, $firstAlbumId);
            $this->artists->albumTracks($user, $firstAlbumId);
            $this->insights->album($user, $firstAlbumId);
        }
    }
}
