<?php

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\Track;
use App\Models\User;
use App\Services\Spotify\Library\SpotifyLibraryService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('sync playlist tracks reads spotify item id payload and hydrates local track id', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-items-1',
        'tracks_total' => 0,
    ]);

    $track = Track::query()->create([
        'spotify_id' => 'track-item-1',
        'name' => 'Item Track',
        'duration_ms' => 123000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token-1');

    Http::fake([
        'api.spotify.com/v1/playlists/playlist-items-1/items*' => Http::response([
            'items' => [
                [
                    'item' => [
                        'id' => 'track-item-1',
                    ],
                    'added_at' => '2020-07-14T01:11:10Z',
                    'added_by' => ['id' => 'spotify-user-1'],
                ],
            ],
            'next' => null,
        ], 200),
    ]);

    $service = new SpotifyLibraryService($tokenService);
    $service->syncPlaylistTracks($user, $playlist);

    $syncedRow = PlaylistTrack::query()
        ->where('playlist_id', $playlist->id)
        ->first();

    expect($syncedRow)
        ->not->toBeNull()
        ->and($syncedRow->spotify_track_id)->toBe('track-item-1')
        ->and($syncedRow->track_id)->toBe($track->id)
        ->and($syncedRow->added_at?->format('Y-m-d H:i:s'))->toBe('2020-07-14 01:11:10');
});

test('sync playlist tracks keeps existing cache when spotify returns only forbidden responses', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-forbidden-1',
    ]);

    PlaylistTrack::factory()->create([
        'playlist_id' => $playlist->id,
        'spotify_track_id' => 'track-existing',
        'position' => 0,
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token-1');

    Http::fake([
        'api.spotify.com/v1/playlists/playlist-forbidden-1/items*' => Http::response([
            'error' => ['status' => 403, 'message' => 'Forbidden'],
        ], 403),
    ]);

    $service = new SpotifyLibraryService($tokenService);
    $service->syncPlaylistTracks($user, $playlist);

    expect(PlaylistTrack::query()->where('playlist_id', $playlist->id)->count())->toBe(1)
        ->and(PlaylistTrack::query()->where('playlist_id', $playlist->id)->first()?->spotify_track_id)->toBe('track-existing');
});

test('sync playlist tracks retries without market when token market request is forbidden', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-fallback-1',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token-1');

    Http::fake(function (Request $request) {
        if (! str_contains($request->url(), '/v1/playlists/playlist-fallback-1/items')) {
            return Http::response([], 404);
        }

        if (str_contains($request->url(), 'market=from_token')) {
            return Http::response([
                'error' => ['status' => 403, 'message' => 'Forbidden'],
            ], 403);
        }

        return Http::response([
            'items' => [
                [
                    'item' => ['id' => 'track-fallback-1'],
                ],
            ],
            'next' => null,
        ], 200);
    });

    $service = new SpotifyLibraryService($tokenService);
    $service->syncPlaylistTracks($user, $playlist);

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/v1/playlists/playlist-fallback-1/items')
            && str_contains($request->url(), 'market=from_token');
    });

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/v1/playlists/playlist-fallback-1/items')
            && ! str_contains($request->url(), 'market=from_token');
    });

    expect(PlaylistTrack::query()->where('playlist_id', $playlist->id)->count())->toBe(1)
        ->and(PlaylistTrack::query()->where('playlist_id', $playlist->id)->first()?->spotify_track_id)->toBe('track-fallback-1');
});

test('sync playlist tracks accepts legacy track.id payload shape', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-legacy-shape-1',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token-1');

    Http::fake([
        'api.spotify.com/v1/playlists/playlist-legacy-shape-1/items*' => Http::response([
            'items' => [
                [
                    'track' => [
                        'id' => 'track-legacy-1',
                    ],
                ],
            ],
            'next' => null,
        ], 200),
    ]);

    $service = new SpotifyLibraryService($tokenService);
    $service->syncPlaylistTracks($user, $playlist);

    expect(PlaylistTrack::query()->where('playlist_id', $playlist->id)->count())->toBe(1)
        ->and(PlaylistTrack::query()->where('playlist_id', $playlist->id)->first()?->spotify_track_id)->toBe('track-legacy-1');
});

test('sync playlist tracks stores null added_at when spotify returns invalid timestamp', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-invalid-ts-1',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token-1');

    Http::fake([
        'api.spotify.com/v1/playlists/playlist-invalid-ts-1/items*' => Http::response([
            'items' => [
                [
                    'item' => [
                        'id' => 'track-item-invalid-ts',
                    ],
                    'added_at' => 'not-a-date',
                ],
            ],
            'next' => null,
        ], 200),
    ]);

    $service = new SpotifyLibraryService($tokenService);
    $service->syncPlaylistTracks($user, $playlist);

    $syncedRow = PlaylistTrack::query()
        ->where('playlist_id', $playlist->id)
        ->first();

    expect($syncedRow)
        ->not->toBeNull()
        ->and($syncedRow->spotify_track_id)->toBe('track-item-invalid-ts')
        ->and($syncedRow->added_at)->toBeNull();
});

test('sync user playlists assigns default position when playlist is first created', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token-1');

    Http::fake([
        'api.spotify.com/v1/me/playlists*' => Http::response([
            'items' => [
                [
                    'id' => 'playlist-positioned-1',
                    'name' => 'Positioned Playlist',
                    'description' => null,
                    'images' => [],
                    'owner' => [
                        'id' => 'owner-1',
                        'display_name' => 'Owner Name',
                    ],
                    'public' => false,
                    'collaborative' => false,
                    'tracks' => ['total' => 1],
                    'snapshot_id' => 'snapshot-1',
                    'uri' => 'spotify:playlist:playlist-positioned-1',
                    'external_urls' => ['spotify' => 'https://open.spotify.com/playlist/playlist-positioned-1'],
                ],
            ],
            'next' => null,
        ], 200),
    ]);

    $service = new SpotifyLibraryService($tokenService);
    $service->syncUserPlaylists($user);

    $playlist = Playlist::query()
        ->where('user_id', $user->id)
        ->where('spotify_id', 'playlist-positioned-1')
        ->first();

    expect($playlist)
        ->not->toBeNull()
        ->and($playlist->position)->toBeGreaterThan(0);
});

test('sync playlist tracks hydrates unknown tracks into local catalog', function () {
    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-hydrate-catalog-1',
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token-1');

    Http::fake([
        'api.spotify.com/v1/playlists/playlist-hydrate-catalog-1/items*' => Http::response([
            'items' => [
                [
                    'item' => [
                        'id' => 'track-catalog-1',
                        'name' => 'Catalog Track',
                        'duration_ms' => 215000,
                        'explicit' => false,
                        'album' => [
                            'id' => 'album-catalog-1',
                            'name' => 'Catalog Album',
                            'album_type' => 'album',
                            'release_date' => '2020-01-01',
                            'images' => [
                                ['url' => 'https://example.com/album.jpg', 'height' => 300, 'width' => 300],
                            ],
                            'total_tracks' => 8,
                        ],
                        'artists' => [
                            [
                                'id' => 'artist-catalog-1',
                                'name' => 'Catalog Artist',
                                'images' => [],
                                'genres' => [],
                                'external_urls' => ['spotify' => 'https://open.spotify.com/artist/artist-catalog-1'],
                            ],
                        ],
                    ],
                ],
            ],
            'next' => null,
        ], 200),
    ]);

    $service = new SpotifyLibraryService($tokenService);
    $service->syncPlaylistTracks($user, $playlist);

    $syncedRow = PlaylistTrack::query()
        ->where('playlist_id', $playlist->id)
        ->first();

    expect($syncedRow)
        ->not->toBeNull()
        ->and($syncedRow->track_id)->not->toBeNull()
        ->and(Track::query()->where('spotify_id', 'track-catalog-1')->exists())->toBeTrue();
});
