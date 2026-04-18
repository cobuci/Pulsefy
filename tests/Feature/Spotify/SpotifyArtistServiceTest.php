<?php

use App\Models\User;
use App\Services\Spotify\Artist\SpotifyArtistService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('artist returns artist payload when spotify responds with 200', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/artists/artist-1' => Http::response([
            'id' => 'artist-1',
            'name' => 'Artist One',
            'images' => [],
            'genres' => [],
            'external_urls' => ['spotify' => 'https://open.spotify.com/artist/artist-1'],
        ]),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->artist($user, 'artist-1'))
        ->toMatchArray([
            'id' => 'artist-1',
            'name' => 'Artist One',
        ]);
});

test('artist returns null when spotify responds with 404', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/artists/missing-artist' => Http::response([
            'error' => ['status' => 404, 'message' => 'Not found'],
        ], 404),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->artist($user, 'missing-artist'))->toBeNull();
});

test('topTracks returns tracks payload', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/artists/artist-1/top-tracks*' => Http::response([
            'tracks' => [
                [
                    'id' => 'track-1',
                    'name' => 'Track One',
                    'artists' => [['id' => 'artist-1', 'name' => 'Artist One']],
                ],
            ],
        ]),
    ]);

    $service = new SpotifyArtistService($tokenService);
    $tracks = $service->topTracks($user, 'artist-1');

    expect($tracks)
        ->toHaveCount(1)
        ->and(data_get($tracks, '0.id'))->toBe('track-1')
        ->and(data_get($tracks, '0.name'))->toBe('Track One');
});

test('topTracks falls back to search when top-tracks endpoint is forbidden', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->times(3)->andReturn('app-token');
    $tokenService->shouldReceive('ensureFreshToken')->never();

    Http::fake([
        'api.spotify.com/v1/artists/artist-1/top-tracks*' => Http::sequence()
            ->push(['error' => ['status' => 403, 'message' => 'Forbidden']], 403)
            ->push(['error' => ['status' => 403, 'message' => 'Forbidden']], 403),
        'api.spotify.com/v1/artists/artist-1' => Http::response([
            'id' => 'artist-1',
            'name' => 'Artist One',
            'images' => [],
            'genres' => ['rock'],
            'external_urls' => ['spotify' => 'https://open.spotify.com/artist/artist-1'],
        ]),
        'api.spotify.com/v1/search*' => Http::response([
            'tracks' => [
                'items' => [
                    [
                        'id' => 'track-1',
                        'name' => 'Track One',
                        'artists' => [['id' => 'artist-1', 'name' => 'Artist One']],
                    ],
                    [
                        'id' => 'track-2',
                        'name' => 'Track Two',
                        'artists' => [['id' => 'other-artist', 'name' => 'Other Artist']],
                    ],
                ],
            ],
        ]),
    ]);

    $service = new SpotifyArtistService($tokenService);
    $tracks = $service->topTracks($user, 'artist-1');

    expect($tracks)
        ->toHaveCount(1)
        ->and(data_get($tracks, '0.id'))->toBe('track-1');
});

test('albums returns album items payload', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/artists/artist-1/albums*' => Http::response([
            'items' => [
                ['id' => 'album-1', 'name' => 'Album One'],
            ],
        ]),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->albums($user, 'artist-1'))
        ->toBe([['id' => 'album-1', 'name' => 'Album One']]);
});

test('album returns album payload', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/albums/album-1*' => Http::response([
            'id' => 'album-1',
            'name' => 'Album One',
            'images' => [],
            'release_date' => '2024-01-01',
        ]),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->album($user, 'album-1'))
        ->toMatchArray([
            'id' => 'album-1',
            'name' => 'Album One',
        ]);
});

test('albumTracks returns items payload', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/albums/album-1/tracks*' => Http::response([
            'items' => [
                ['id' => 'track-1', 'name' => 'Track One'],
            ],
        ]),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->albumTracks($user, 'album-1'))
        ->toBe([['id' => 'track-1', 'name' => 'Track One']]);
});
