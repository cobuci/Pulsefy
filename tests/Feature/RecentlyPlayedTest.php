<?php

use App\Models\Album;
use App\Models\Artist;
use App\Models\Track;
use App\Models\User;
use App\Models\UserRecentPlay;
use App\Services\Spotify\SpotifyTokenService;
use Inertia\Testing\AssertableInertia;

test('recently played page resolves groups from db snapshots without spotify api calls', function () {
    $tokenService = Mockery::mock(SpotifyTokenService::class);
    $tokenService->shouldNotReceive('ensureFreshToken');
    $tokenService->shouldNotReceive('appAccessToken');
    app()->instance(SpotifyTokenService::class, $tokenService);

    $user = User::factory()->create();

    $artist = Artist::query()->create([
        'artist_id' => 'artist-1',
        'artist_name' => 'Artist One',
        'genres' => ['rock'],
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

    UserRecentPlay::query()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
        'played_at' => now()->subMinutes(2),
    ]);

    UserRecentPlay::query()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
        'played_at' => now()->subMinutes(1),
    ]);

    $this->actingAs($user)
        ->get(route('recently-played'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('RecentlyPlayed')
            ->missing('playGroups')
            ->loadDeferredProps(fn (AssertableInertia $reload) => $reload
                ->has('playGroups', 1)
                ->where('playGroups.0.entries.0.track.id', 'track-1')
                ->where('playGroups.0.entries.0.count', 2)
            )
        );
});
