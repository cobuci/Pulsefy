<?php

namespace App\Services\Spotify\Support;

final class NullGlobalSpotifyRateLimit
{
    public function throttle(): void {}
}
