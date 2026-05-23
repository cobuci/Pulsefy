<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyStat;
use App\Models\Track;
use App\Models\User;
use App\Models\UserTopArtist;
use App\Services\LastFm\LastFmClient;
use App\Services\LastFm\LastFmGenreService;
use App\Services\Spotify\Artist\ArtistGenreCacheService;
use App\Services\Spotify\SpotifyService;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

function makeSpotifyService(SpotifyTokenService $tokenService): SpotifyService
{
    return new SpotifyService(
        $tokenService,
        new ArtistGenreCacheService(new LastFmGenreService(new LastFmClient)),
    );
}

test('returns cached data when stat exists and is not expired', function () {
    $user = User::factory()->create();

    SpotifyStat::factory()->forTopTracks()->create([
        'user_id' => $user->id,
        'payload' => [['id' => 'track1', 'name' => 'Cached Track']],
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldNotReceive('ensureFreshToken');

    $service = makeSpotifyService($tokenService);
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

    $service = makeSpotifyService($tokenService);
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

    $service = makeSpotifyService($tokenService);
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

    $service = makeSpotifyService($tokenService);
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

    $service = makeSpotifyService($tokenService);
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

    $service = makeSpotifyService($tokenService);

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

    $service = makeSpotifyService($tokenService);

    expect($service->topTracks($user))->toBe([]);
});

test('topTracks returns empty array gracefully when api is unreachable', function () {
    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->once()->andReturn('token');

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::failedConnection(),
    ]);

    $service = makeSpotifyService($tokenService);

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

    $service = makeSpotifyService($tokenService);

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

    $service = makeSpotifyService($tokenService);

    expect($service->recentlyPlayed($user))->toBe([]);
});

test('topItemsSnapshot fetches paginated top tracks and artists', function () {
    config()->set('services.lastfm.api_key', 'test-key');

    $user = User::factory()->create();

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldReceive('ensureFreshToken')->times(6)->andReturn('token');

    Http::fake([
        'ws.audioscrobbler.com/2.0/*' => Http::response([
            'toptags' => [
                'tag' => [
                    ['name' => 'rock', 'count' => 100],
                ],
            ],
        ]),
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
                        fn (int $i): array => [
                            'id' => 'artist-'.$i,
                            'name' => 'Artist '.$i,
                            'genres' => [],
                        ],
                        range(1, 50),
                    ),
                ]);
            }

            return Http::response([
                'items' => [
                    ['id' => 'artist-51', 'name' => 'Artist 51', 'genres' => []],
                ],
            ]);
        },
    ]);

    $service = makeSpotifyService($tokenService);
    $snapshot = $service->topItemsSnapshot($user);

    expect($snapshot)
        ->toHaveKeys(['short_term', 'medium_term', 'long_term'])
        ->and(data_get($snapshot, 'medium_term.tracks'))->toHaveCount(52)
        ->and(data_get($snapshot, 'medium_term.artists'))->toHaveCount(51)
        ->and(data_get($snapshot, 'medium_term.artists.0.genres.0'))->toBe('rock');
});

test('topArtists from db falls back to album art when artist image is missing', function () {
    $user = User::factory()->create();

    $artist = Artist::query()->create([
        'artist_id' => 'artist-1',
        'artist_name' => 'Artist One',
        'genres' => ['rock'],
        'images' => null,
        'fetched_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $album = Album::query()->create([
        'spotify_id' => 'album-1',
        'name' => 'Album One',
        'album_type' => 'album',
        'release_date' => '2024-01-01',
        'images' => [['url' => 'https://image.test/album.jpg', 'height' => 640, 'width' => 640]],
        'total_tracks' => 10,
        'metadata_synced_at' => now(),
    ]);

    $track = Track::query()->create([
        'spotify_id' => 'track-1',
        'album_id' => $album->id,
        'name' => 'Track One',
        'duration_ms' => 120000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    $track->artists()->syncWithoutDetaching([$artist->id]);

    UserTopArtist::query()->create([
        'user_id' => $user->id,
        'artist_model_id' => $artist->id,
        'time_range' => 'medium_term',
        'rank' => 1,
        'score' => 100,
        'synced_at' => now(),
    ]);

    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldNotReceive('ensureFreshToken');

    $service = makeSpotifyService($tokenService);
    $artists = $service->topArtists($user, 'medium_term');

    expect($artists)
        ->toHaveCount(1)
        ->and(data_get($artists, '0.images.0.url'))
        ->toBe('https://image.test/album.jpg');
});
