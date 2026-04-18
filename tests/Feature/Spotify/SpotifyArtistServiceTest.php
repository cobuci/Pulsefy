<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use App\Services\Spotify\Artist\SpotifyArtistService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
    Cache::flush();
});

test('artist returns artist payload when spotify responds with 200', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

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
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

    Http::fake([
        'api.spotify.com/v1/artists/missing-artist' => Http::response([
            'error' => ['status' => 404, 'message' => 'Not found'],
        ], 404),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->artist($user, 'missing-artist'))->toBeNull();
});

test('topTracks returns tracks payload', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    Artist::query()->create([
        'artist_id' => 'artist-1',
        'artist_name' => 'Artist One',
        'genres' => [],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'tracks' => [
                'items' => [
                    [
                        'id' => 'track-1',
                        'name' => 'Track One',
                        'artists' => [['id' => 'artist-1', 'name' => 'Artist One']],
                    ],
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

    $artist = Artist::query()->create([
        'artist_id' => 'artist-1',
        'artist_name' => 'Artist One',
        'genres' => ['rock'],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $album = Album::query()->create([
        'spotify_id' => 'album-1',
        'name' => 'Album One',
        'album_type' => 'album',
        'release_date' => '2024-01-01',
        'images' => [],
        'total_tracks' => 10,
        'metadata_synced_at' => now(),
    ]);

    $track = Track::query()->create([
        'spotify_id' => 'track-1',
        'album_id' => $album->id,
        'name' => 'Track One',
        'duration_ms' => 180000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    $track->artists()->syncWithoutDetaching([$artist->id]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldNotReceive('appAccessToken');
    $tokenService->shouldNotReceive('ensureFreshToken');

    $service = new SpotifyArtistService($tokenService);
    $tracks = $service->topTracks($user, 'artist-1');

    expect($tracks)
        ->toHaveCount(1)
        ->and(data_get($tracks, '0.id'))->toBe('track-1');
});

test('albums returns album items payload', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

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
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

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
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

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

test('isArtistFollowed returns true when spotify contains endpoint returns true', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

    Http::fake([
        'api.spotify.com/v1/me/library/contains*' => Http::response([true]),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->isArtistFollowed($user, 'artist-1'))->toBeTrue();
});

test('followArtist returns true when spotify accepts follow', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

    Http::fake([
        'api.spotify.com/v1/me/library*' => Http::response(null, 200),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->followArtist($user, 'artist-1'))->toBeTrue();
});

test('isAlbumSaved returns true when spotify contains endpoint returns true', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

    Http::fake([
        'api.spotify.com/v1/me/library/contains*' => Http::response([true]),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->isAlbumSaved($user, 'album-1'))->toBeTrue();
});

test('saveAlbum returns true when spotify accepts save', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('user-token');

    Http::fake([
        'api.spotify.com/v1/me/library*' => Http::response(null, 200),
    ]);

    $service = new SpotifyArtistService($tokenService);

    expect($service->saveAlbum($user, 'album-1'))->toBeTrue();
});

test('albums returns hydrated db fallback when spotify is rate limited', function () {
    $user = User::factory()->create([
        'spotify_token' => 'user-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    $artist = Artist::query()->create([
        'artist_id' => 'artist-1',
        'artist_name' => 'Artist One',
        'genres' => ['alt pop'],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $album = Album::query()->create([
        'spotify_id' => 'album-1',
        'name' => 'Album One',
        'album_type' => 'album',
        'release_date' => '2024-01-01',
        'images' => [],
        'total_tracks' => 10,
        'metadata_synced_at' => now(),
    ]);

    $artist->albums()->syncWithoutDetaching([$album->id]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('appAccessToken')->zeroOrMoreTimes();
    $tokenService->shouldReceive('ensureFreshToken')->zeroOrMoreTimes();

    $service = new SpotifyArtistService($tokenService);

    $albums = $service->albums($user, 'artist-1');

    expect($albums)
        ->toHaveCount(1)
        ->and(data_get($albums, '0.id'))->toBe('album-1');
});
