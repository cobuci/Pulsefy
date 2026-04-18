<?php

use App\Models\Artist;
use App\Models\SpotifySyncRun;
use App\Models\Track;
use App\Models\User;
use App\Models\UserTopArtist;
use App\Models\UserTopTrack;
use App\Services\Spotify\SpotifyTokenService;
use App\Services\Spotify\Sync\SpotifySyncService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('sync top tracks prunes stale ranking rows for a time range', function () {
    $user = User::factory()->create();

    $keepTrack = Track::query()->create([
        'spotify_id' => 'track-keep',
        'name' => 'Keep Track',
        'duration_ms' => 120000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    $staleTrack = Track::query()->create([
        'spotify_id' => 'track-stale',
        'name' => 'Stale Track',
        'duration_ms' => 120000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    UserTopTrack::query()->create([
        'user_id' => $user->id,
        'track_id' => $keepTrack->id,
        'time_range' => 'medium_term',
        'rank' => 1,
        'score' => 50,
        'synced_at' => now()->subDay(),
    ]);

    UserTopTrack::query()->create([
        'user_id' => $user->id,
        'track_id' => $staleTrack->id,
        'time_range' => 'medium_term',
        'rank' => 2,
        'score' => 49,
        'synced_at' => now()->subDay(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => function (Request $request) {
            $range = data_get($request->data(), 'time_range');

            if ($range !== 'medium_term') {
                return Http::response(['items' => []]);
            }

            return Http::response([
                'items' => [[
                    'id' => 'track-keep',
                    'name' => 'Keep Track',
                    'duration_ms' => 120000,
                    'explicit' => false,
                    'artists' => [[
                        'id' => 'artist-1',
                        'name' => 'Artist One',
                    ]],
                    'album' => [
                        'id' => 'album-1',
                        'name' => 'Album One',
                        'album_type' => 'album',
                        'release_date' => '2024-01-01',
                        'images' => [],
                        'total_tracks' => 10,
                    ],
                ]],
            ]);
        },
    ]);

    (new SpotifySyncService($tokenService))->syncTopTracks($user);

    expect(UserTopTrack::query()
        ->where('user_id', $user->id)
        ->where('time_range', 'medium_term')
        ->count())->toBe(1)
        ->and(UserTopTrack::query()
            ->where('user_id', $user->id)
            ->where('time_range', 'medium_term')
            ->where('track_id', $keepTrack->id)
            ->exists())->toBeTrue();
});

test('sync top artists prunes stale ranking rows for a time range', function () {
    $user = User::factory()->create();

    $keepArtist = Artist::query()->create([
        'artist_id' => 'artist-keep',
        'artist_name' => 'Keep Artist',
        'genres' => [],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $staleArtist = Artist::query()->create([
        'artist_id' => 'artist-stale',
        'artist_name' => 'Stale Artist',
        'genres' => [],
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    UserTopArtist::query()->create([
        'user_id' => $user->id,
        'artist_model_id' => $keepArtist->id,
        'time_range' => 'medium_term',
        'rank' => 1,
        'score' => 50,
        'synced_at' => now()->subDay(),
    ]);

    UserTopArtist::query()->create([
        'user_id' => $user->id,
        'artist_model_id' => $staleArtist->id,
        'time_range' => 'medium_term',
        'rank' => 2,
        'score' => 49,
        'synced_at' => now()->subDay(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/artists*' => function (Request $request) {
            $range = data_get($request->data(), 'time_range');

            if ($range !== 'medium_term') {
                return Http::response(['items' => []]);
            }

            return Http::response([
                'items' => [[
                    'id' => 'artist-keep',
                    'name' => 'Keep Artist',
                    'genres' => ['alt pop'],
                ]],
            ]);
        },
    ]);

    (new SpotifySyncService($tokenService))->syncTopArtists($user);

    expect(UserTopArtist::query()
        ->where('user_id', $user->id)
        ->where('time_range', 'medium_term')
        ->count())->toBe(1)
        ->and(UserTopArtist::query()
            ->where('user_id', $user->id)
            ->where('time_range', 'medium_term')
            ->where('artist_model_id', $keepArtist->id)
            ->exists())->toBeTrue();
});

test('sync runs persist meta counts and duration information', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/recently-played*' => Http::response([
            'items' => [
                [
                    'played_at' => now()->subMinute()->toIso8601String(),
                    'track' => [
                        'id' => 'track-meta',
                        'name' => 'Meta Track',
                        'duration_ms' => 123000,
                        'explicit' => false,
                        'artists' => [
                            ['id' => 'artist-meta', 'name' => 'Meta Artist'],
                        ],
                        'album' => [
                            'id' => 'album-meta',
                            'name' => 'Meta Album',
                            'album_type' => 'album',
                            'release_date' => '2024-01-01',
                            'images' => [],
                            'total_tracks' => 9,
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    (new SpotifySyncService($tokenService))->syncRecentPlays($user);

    $run = SpotifySyncRun::query()
        ->where('user_id', $user->id)
        ->where('type', 'recent_plays')
        ->latest('id')
        ->first();

    expect($run)->not->toBeNull()
        ->and($run?->status)->toBe('completed')
        ->and(data_get($run?->meta, 'fetched'))->toBe(1)
        ->and(data_get($run?->meta, 'upserted'))->toBe(1)
        ->and(data_get($run?->meta, 'skipped'))->toBe(0)
        ->and((int) data_get($run?->meta, 'duration_ms'))->toBeGreaterThanOrEqual(0);
});
