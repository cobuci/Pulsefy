<?php

use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\Track;
use App\Models\User;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Inertia\Testing\AssertableInertia;

test('guests are redirected from library playlist page', function () {
    $this->get(route('library.show', ['playlistId' => 'playlist-1']))
        ->assertRedirect(route('login'));
});

test('library show displays cached playlist details and track cache', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-1',
        'name' => 'Daily Mix',
        'tracks_total' => 2,
        'expires_at' => now()->addMinutes(30),
    ]);

    PlaylistTrack::factory()->create([
        'playlist_id' => $playlist->id,
        'spotify_track_id' => 'track-abc',
        'position' => 0,
    ]);

    PlaylistTrack::factory()->create([
        'playlist_id' => $playlist->id,
        'spotify_track_id' => 'track-def',
        'position' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('library.show', ['playlistId' => 'playlist-1']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Show')
            ->where('playlist.id', 'playlist-1')
            ->where('playlist.name', 'Daily Mix')
            ->where('playlist.items.0.spotify_track_id', 'track-abc')
            ->where('playlist.items.1.spotify_track_id', 'track-def')
        );
});

test('library show includes hydrated local track details when resolved', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-hydrated',
        'name' => 'Hydrated Playlist',
        'expires_at' => now()->addMinutes(30),
    ]);

    $track = Track::query()->create([
        'spotify_id' => 'track-known',
        'name' => 'Known Track',
        'duration_ms' => 120000,
        'explicit' => false,
        'metadata_synced_at' => now(),
    ]);

    PlaylistTrack::factory()->create([
        'playlist_id' => $playlist->id,
        'track_id' => $track->id,
        'spotify_track_id' => 'track-known',
        'position' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('library.show', ['playlistId' => 'playlist-hydrated']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Show')
            ->where('playlist.id', 'playlist-hydrated')
            ->where('playlist.items.0.spotify_track_id', 'track-known')
            ->where('playlist.items.0.track.id', 'track-known')
            ->where('playlist.items.0.track.name', 'Known Track')
        );
});

test('library show refreshes stale playlist tracks', function () {
    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-stale',
        'expires_at' => now()->subMinute(),
    ]);

    $service = Mockery::mock(SpotifyLibraryService::class);
    $service->shouldReceive('syncPlaylistTracks')
        ->once()
        ->andReturnUsing(function (User $modelUser, Playlist $modelPlaylist) use ($user, $playlist): void {
            expect($modelUser->id)->toBe($user->id)
                ->and($modelPlaylist->id)->toBe($playlist->id);

            $modelPlaylist->update([
                'expires_at' => now()->addMinutes(30),
            ]);

            PlaylistTrack::query()->create([
                'playlist_id' => $modelPlaylist->id,
                'spotify_track_id' => 'track-fresh',
                'position' => 0,
            ]);
        });
    app()->instance(SpotifyLibraryService::class, $service);

    $this->actingAs($user)
        ->get(route('library.show', ['playlistId' => 'playlist-stale']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Show')
            ->where('playlist.id', 'playlist-stale')
            ->where('playlist.items.0.spotify_track_id', 'track-fresh')
        );
});
