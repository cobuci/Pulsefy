<?php

namespace App\Services\Spotify\Sync;

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;

final class SpotifyCatalogHydrationService
{
    public function hydrateArtistProfile(array $payload): ?Artist
    {
        return $this->upsertArtist($payload);
    }

    public function hydrateArtistAlbums(Artist $artist, array $albums): void
    {
        foreach ($albums as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $album = $this->upsertAlbum($payload);

            if (! $album) {
                continue;
            }

            $artist->albums()->syncWithoutDetaching([$album->id]);
        }
    }

    public function hydrateTracks(array $tracks, ?Album $album = null): void
    {
        foreach ($tracks as $payload) {
            if (! is_array($payload)) {
                continue;
            }

            $track = $this->upsertTrack($payload, $album);

            if (! $track) {
                continue;
            }

            $artists = data_get($payload, 'artists', []);

            if (! is_array($artists)) {
                continue;
            }

            foreach ($artists as $artistPayload) {
                if (! is_array($artistPayload)) {
                    continue;
                }

                $artist = $this->upsertArtist($artistPayload);

                if (! $artist) {
                    continue;
                }

                $track->artists()->syncWithoutDetaching([$artist->id]);

                if ($track->album_id !== null) {
                    $artist->albums()->syncWithoutDetaching([$track->album_id]);
                }
            }
        }
    }

    public function hydrateAlbumProfile(array $payload): ?Album
    {
        return $this->upsertAlbum($payload);
    }

    public function hydrateAlbumArtists(Album $album, array $payload): void
    {
        $artists = data_get($payload, 'artists', []);

        if (! is_array($artists)) {
            return;
        }

        foreach ($artists as $artistPayload) {
            if (! is_array($artistPayload)) {
                continue;
            }

            $artist = $this->upsertArtist($artistPayload);

            if (! $artist) {
                continue;
            }

            $artist->albums()->syncWithoutDetaching([$album->id]);
        }
    }

    private function upsertArtist(array $payload): ?Artist
    {
        $spotifyId = data_get($payload, 'id');
        $name = data_get($payload, 'name');

        if (! is_string($spotifyId) || $spotifyId === '' || ! is_string($name) || $name === '') {
            return null;
        }

        return Artist::query()->updateOrCreate(
            ['artist_id' => $spotifyId],
            [
                'artist_name' => $name,
                'genres' => is_array(data_get($payload, 'genres')) ? data_get($payload, 'genres') : [],
                'fetched_at' => now(),
                'expires_at' => now()->addDays(7),
            ],
        );
    }

    private function upsertAlbum(array $payload): ?Album
    {
        $spotifyId = data_get($payload, 'id');
        $name = data_get($payload, 'name');

        if (! is_string($spotifyId) || $spotifyId === '' || ! is_string($name) || $name === '') {
            return null;
        }

        return Album::query()->updateOrCreate(
            ['spotify_id' => $spotifyId],
            [
                'name' => $name,
                'album_type' => data_get($payload, 'album_type'),
                'release_date' => data_get($payload, 'release_date'),
                'images' => is_array(data_get($payload, 'images')) ? data_get($payload, 'images') : null,
                'total_tracks' => (int) data_get($payload, 'total_tracks', 0),
                'metadata_synced_at' => now(),
            ],
        );
    }

    private function upsertTrack(array $payload, ?Album $album = null): ?Track
    {
        $spotifyId = data_get($payload, 'id');
        $name = data_get($payload, 'name');

        if (! is_string($spotifyId) || $spotifyId === '' || ! is_string($name) || $name === '') {
            return null;
        }

        $resolvedAlbum = $album;

        if (! $resolvedAlbum) {
            $albumPayload = data_get($payload, 'album');

            if (is_array($albumPayload)) {
                $resolvedAlbum = $this->upsertAlbum($albumPayload);
            }
        }

        return Track::query()->updateOrCreate(
            ['spotify_id' => $spotifyId],
            [
                'album_id' => $resolvedAlbum?->id,
                'name' => $name,
                'duration_ms' => (int) data_get($payload, 'duration_ms', 0),
                'explicit' => (bool) data_get($payload, 'explicit', false),
                'metadata_synced_at' => now(),
            ],
        );
    }
}
