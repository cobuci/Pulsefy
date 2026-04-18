<?php

namespace App\Services\Spotify\Support;

use Illuminate\Support\Facades\Redis;

final class GlobalSpotifyRateLimit
{
    private const int WINDOW_SECONDS = 1;

    private const int MAX_REQUESTS_PER_WINDOW = 8;

    public function throttle(): void
    {
        $key = 'spotify:global-rate-limit:'.now()->timestamp;
        $count = Redis::incr($key);

        if ($count === 1) {
            Redis::expire($key, self::WINDOW_SECONDS);
        }

        if ($count > self::MAX_REQUESTS_PER_WINDOW) {
            usleep(200000);
        }
    }
}
