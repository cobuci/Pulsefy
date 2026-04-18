<?php

namespace App\Services\Spotify\Contracts;

use App\Models\User;

interface SpotifyPlaybackProvider
{
    public function currentlyPlaying(User $user): ?array;

    public function devices(User $user): array;

    public function resumePlay(User $user): bool;

    public function play(User $user, string $uri): bool;

    public function pause(User $user): bool;

    public function next(User $user): bool;

    public function previous(User $user): bool;

    public function seek(User $user, int $positionMs): bool;

    public function setVolume(User $user, int $volumePercent): bool;

    public function transferPlayback(User $user, string $deviceId, bool $play = true): bool;
}
