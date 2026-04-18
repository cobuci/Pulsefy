<?php

use App\Jobs\RunUserSpotifySyncJob;
use App\Models\Album;
use App\Models\Artist;
use App\Models\SpotifyStat;
use App\Models\SpotifySyncRun;
use App\Models\Track;
use App\Models\User;
use App\Models\UserRecentPlay;
use App\Models\UserTopArtist;
use App\Models\UserTopTrack;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

test('dashboard renders with correct inertia component', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->has('period')
        );
});

test('dashboard defaults to medium_term period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'medium_term')
        );
});

test('dashboard accepts valid period query param', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['period' => 'short_term']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'short_term')
        );
});

test('dashboard falls back to medium_term for invalid period', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['period' => 'invalid']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('period', 'medium_term')
        );
});

test('dashboard response includes deferred spotify prop keys', function () {
    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    Http::fake([
        'api.spotify.com/v1/me/top/tracks*' => Http::response(['items' => []]),
        'api.spotify.com/v1/me/top/artists*' => Http::response(['items' => []]),
        'api.spotify.com/v1/me/player/recently-played*' => Http::response(['items' => []]),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->missing('topTracks')
            ->missing('topArtists')
            ->missing('recentPlays')
            ->missing('insights')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('topTracks')
                ->has('topArtists')
                ->has('recentPlays')
                ->has('insights')
                ->where('insights.topGenre', 'Mixed')
                ->where('insights.topGenres', [])
            )
        );
});

test('authenticated users can refresh all spotify insight caches manually', function () {
    Bus::fake();

    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    SpotifyStat::query()->create([
        'user_id' => $user->id,
        'type' => 'top_tracks',
        'time_range' => 'medium_term',
        'payload' => [['id' => 'old-track']],
        'fetched_at' => now()->subDay(),
        'expires_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->post(route('insights.refresh'))
        ->assertRedirect();

    Bus::assertDispatched(RunUserSpotifySyncJob::class, function (RunUserSpotifySyncJob $job) use ($user): bool {
        return $job->userId === $user->id;
    });

    expect(SpotifyStat::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('dashboard includes sync status prop for current user', function () {
    $user = User::factory()->create();

    SpotifySyncRun::query()->create([
        'user_id' => $user->id,
        'type' => 'top_tracks',
        'status' => 'running',
        'started_at' => now()->subSeconds(10),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('syncStatus.isRunning', true)
            ->where('syncStatus.total', 3)
        );
});

test('dashboard resolves deferred props from db snapshots without spotify api calls', function () {
    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldNotReceive('ensureFreshToken');
    $tokenService->shouldNotReceive('appAccessToken');
    app()->instance(SpotifyTokenService::class, $tokenService);

    $user = User::factory()->create();

    $artist = Artist::query()->create([
        'artist_id' => 'artist-1',
        'artist_name' => 'Artist One',
        'genres' => ['alt pop'],
        'fetched_at' => now(),
        'expires_at' => now()->addDays(7),
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

    UserTopArtist::query()->create([
        'user_id' => $user->id,
        'artist_model_id' => $artist->id,
        'time_range' => 'medium_term',
        'rank' => 1,
        'score' => 100,
        'synced_at' => now(),
    ]);

    UserTopTrack::query()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
        'time_range' => 'medium_term',
        'rank' => 1,
        'score' => 100,
        'synced_at' => now(),
    ]);

    UserRecentPlay::query()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
        'played_at' => now()->subMinutes(3),
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Dashboard')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->where('topTracks.0.id', 'track-1')
                ->where('topArtists.0.id', 'artist-1')
                ->where('recentPlays.0.track.id', 'track-1')
                ->where('insights.topGenre', 'alt pop')
            )
        );
});
