<?php

use App\Services\Spotify\Client\SpotifyArtistClient;
use App\Services\Spotify\Support\GlobalSpotifyRateLimit;
use Illuminate\Support\Facades\Http;

test('artist client retries on 429 and eventually succeeds', function () {
    app()->bind(GlobalSpotifyRateLimit::class, fn (): object => new class
    {
        public function throttle(): void {}
    });

    Http::fake([
        'api.spotify.com/v1/artists/artist-1*' => Http::sequence()
            ->push('Too many requests', 429, ['Retry-After' => '0'])
            ->push([
                'id' => 'artist-1',
                'name' => 'Artist One',
            ], 200),
    ]);

    $response = (new SpotifyArtistClient('token'))->artist('artist-1');

    expect($response->status())->toBe(200)
        ->and($response->json('id'))->toBe('artist-1');

    Http::assertSentCount(2);
});
