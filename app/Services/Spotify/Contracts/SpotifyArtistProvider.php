<?php

namespace App\Services\Spotify\Contracts;

use App\Models\User;

interface SpotifyArtistProvider
{
    public function artist(User $user, string $artistId): ?array;

    public function topTracks(User $user, string $artistId): array;

    public function albums(User $user, string $artistId): array;

    public function album(User $user, string $albumId): ?array;

    public function albumTracks(User $user, string $albumId): array;

    public function isArtistFollowed(User $user, string $artistId): bool;

    public function followArtist(User $user, string $artistId): bool;

    public function unfollowArtist(User $user, string $artistId): bool;

    public function isAlbumSaved(User $user, string $albumId): bool;

    public function saveAlbum(User $user, string $albumId): bool;

    public function unsaveAlbum(User $user, string $albumId): bool;
}
