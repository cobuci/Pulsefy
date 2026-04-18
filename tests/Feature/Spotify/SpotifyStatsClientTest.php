<?php

use App\Services\Spotify\Client\SpotifyStatsClient;
use App\Services\Spotify\Support\GlobalSpotifyRateLimit;
use Illuminate\Support\Facades\Http;

test('stats client retries 429 responses and succeeds within max attempts', function () {
    app()->bind(GlobalSpotifyRateLimit::class, fn (): object => new class
    {
        public function throttle(): void {}
    });

    Http::fake([
        'api.spotify.com/v1/me/top/artists*' => Http::sequence()
            ->push(['error' => ['status' => 429]], 429, ['Retry-After' => '0'])
            ->push(['error' => ['status' => 429]], 429, ['Retry-After' => '0'])
            ->push(['items' => [['id' => 'artist-1']]], 200),
    ]);

    $response = (new SpotifyStatsClient('token'))->topArtistsPage('medium_term', 50, 0);

    expect($response->status())->toBe(200)
        ->and($response->json('items.0.id'))->toBe('artist-1');

    Http::assertSentCount(3);
});

test('stats client returns last 429 response after exhausting attempts', function () {
    app()->bind(GlobalSpotifyRateLimit::class, fn (): object => new class
    {
        public function throttle(): void {}
    });

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::sequence()
            ->push(['error' => ['status' => 429]], 429, ['Retry-After' => '0'])
            ->push(['error' => ['status' => 429]], 429, ['Retry-After' => '0'])
            ->push(['error' => ['status' => 429]], 429, ['Retry-After' => '0']),
    ]);

    $response = (new SpotifyStatsClient('token'))->topTracksPage('medium_term', 50, 0);

    expect($response->status())->toBe(429);

    Http::assertSentCount(3);
});
