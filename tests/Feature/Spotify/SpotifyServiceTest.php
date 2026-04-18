<?php

use App\Models\SpotifyStat;
use App\Models\User;
use App\Services\Spotify\SpotifyService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('returns cached data when stat exists and is not expired', function () {
    $user = User::factory()->create();

    SpotifyStat::factory()->forTopTracks()->create([
        'user_id' => $user->id,
        'payload' => [['id' => 'track1', 'name' => 'Cached Track']],
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldNotReceive('ensureFreshToken');

    $service = new SpotifyService($tokenService);
    $result = $service->topTracks($user, 'medium_term');

    expect($result)->toBe([['id' => 'track1', 'name' => 'Cached Track']]);
});

test('fetches from api when cache is expired', function () {
    $user = User::factory()->create();

    SpotifyStat::factory()->forTopTracks()->expired()->create([
        'user_id' => $user->id,
        'payload' => [['id' => 'old_track']],
    ]);

    $freshToken = 'fresh-access-token';

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn($freshToken);

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response([
            'items' => [['id' => 'new_track', 'name' => 'New Track']],
        ]),
    ]);

    $service = new SpotifyService($tokenService);
    $result = $service->topTracks($user, 'medium_term');

    expect($result)->toBe([['id' => 'new_track', 'name' => 'New Track']]);

    $this->assertDatabaseHas('spotify_stats', [
        'user_id' => $user->id,
        'type' => 'top_tracks',
        'time_range' => 'medium_term',
    ]);
});

test('fetches from api when no cache exists', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('some-token');

    Http::fake([
        'api.spotify.com/v1/me/top/artists*' => Http::response([
            'items' => [['id' => 'artist1', 'name' => 'Artist One']],
        ]),
    ]);

    $service = new SpotifyService($tokenService);
    $result = $service->topArtists($user, 'short_term');

    expect($result)->toHaveCount(1)
        ->and($result[0]['name'])->toBe('Artist One');
});

test('recently_played has 15 minute ttl', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/recently-played*' => Http::response([
            'items' => [],
        ]),
    ]);

    $service = new SpotifyService($tokenService);
    $service->recentlyPlayed($user);

    $stat = SpotifyStat::where('user_id', $user->id)
        ->where('type', 'recently_played')
        ->first();

    expect($stat)->not->toBeNull()
        ->and($stat->expires_at->isAfter(now()))->toBeTrue()
        ->and($stat->expires_at->isBefore(now()->addMinutes(16)))->toBeTrue();
});

test('long_term stats have 72 hour ttl', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(['items' => []]),
    ]);

    $service = new SpotifyService($tokenService);
    $service->topTracks($user, 'long_term');

    $stat = SpotifyStat::where('user_id', $user->id)->where('time_range', 'long_term')->first();

    expect($stat->expires_at->isAfter(now()->addHours(71)))->toBeTrue();
});

test('topTracks returns empty array when spotify responds with 401', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'Permissions missing']],
            401,
        ),
    ]);

    $service = new SpotifyService($tokenService);

    expect($service->topTracks($user))->toBe([]);
});

test('topTracks returns empty array when spotify responds with 403', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(
            ['error' => ['status' => 403, 'message' => 'Forbidden']],
            403,
        ),
    ]);

    $service = new SpotifyService($tokenService);

    expect($service->topTracks($user))->toBe([]);
});

test('topTracks returns empty array gracefully when api is unreachable', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::failedConnection(),
    ]);

    $service = new SpotifyService($tokenService);

    expect($service->topTracks($user))->toBe([]);
});

test('topArtists returns empty array when spotify responds with 401', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/artists*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'Permissions missing']],
            401,
        ),
    ]);

    $service = new SpotifyService($tokenService);

    expect($service->topArtists($user))->toBe([]);
});

test('recentlyPlayed returns empty array when spotify responds with 401', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/player/recently-played*' => Http::response(
            ['error' => ['status' => 401, 'message' => 'Permissions missing']],
            401,
        ),
    ]);

    $service = new SpotifyService($tokenService);

    expect($service->recentlyPlayed($user))->toBe([]);
});

test('topItemsSnapshot fetches paginated top tracks and artists', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->times(6)->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => function (Request $request) {
            $offset = (int) ($request->data()['offset'] ?? 0);

            if ($offset === 0) {
                return Http::response([
                    'items' => array_map(
                        fn (int $i): array => ['id' => 'track-'.$i],
                        range(1, 50),
                    ),
                ]);
            }

            return Http::response([
                'items' => [
                    ['id' => 'track-51'],
                    ['id' => 'track-52'],
                ],
            ]);
        },
        'api.spotify.com/v1/me/top/artists*' => function (Request $request) {
            $offset = (int) ($request->data()['offset'] ?? 0);

            if ($offset === 0) {
                return Http::response([
                    'items' => array_map(
                        fn (int $i): array => ['id' => 'artist-'.$i],
                        range(1, 50),
                    ),
                ]);
            }

            return Http::response([
                'items' => [
                    ['id' => 'artist-51'],
                ],
            ]);
        },
    ]);

    $service = new SpotifyService($tokenService);
    $snapshot = $service->topItemsSnapshot($user);

    expect($snapshot)
        ->toHaveKeys(['short_term', 'medium_term', 'long_term'])
        ->and(data_get($snapshot, 'medium_term.tracks'))->toHaveCount(52)
        ->and(data_get($snapshot, 'medium_term.artists'))->toHaveCount(51);
});
