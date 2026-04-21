<?php

use App\Jobs\SyncPlaylistTracksJob;
use App\Models\Artist;
use App\Models\Playlist;
use App\Models\PlaylistTrack;
use App\Models\Track;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
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
            ->where('playlist.sync_status.isRunning', false)
            ->where('items.data.0.spotify_track_id', 'track-abc')
            ->where('items.data.1.spotify_track_id', 'track-def')
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

    $artist = Artist::query()->create([
        'artist_id' => 'artist-known',
        'artist_name' => 'Known Artist',
        'genres' => [],
        'images' => [],
        'popularity' => 0,
        'fetched_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);
    $track->artists()->sync([$artist->id]);

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
            ->where('items.data.0.spotify_track_id', 'track-known')
            ->where('items.data.0.uri', 'spotify:track:track-known')
            ->where('items.data.0.track.id', 'track-known')
            ->where('items.data.0.track.name', 'Known Track')
            ->where('items.data.0.track.artists.0.id', 'artist-known')
            ->where('items.data.0.track.artists.0.name', 'Known Artist')
        );
});

test('library show dispatches playlist sync job when cache is stale', function () {
    Queue::fake();

    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-stale',
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('library.show', ['playlistId' => 'playlist-stale']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Show')
            ->where('playlist.id', 'playlist-stale')
            ->where('playlist.sync_status.isRunning', true)
        );

    Queue::assertPushed(SyncPlaylistTracksJob::class, function (SyncPlaylistTracksJob $job) use ($user, $playlist): bool {
        return $job->userId === $user->id && $job->playlistId === $playlist->spotify_id;
    });
});

test('library show dispatches playlist sync job when cached items are empty', function () {
    Queue::fake();

    $user = User::factory()->create();

    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-empty-items',
        'tracks_total' => 0,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($user)
        ->get(route('library.show', ['playlistId' => 'playlist-empty-items']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Library/Show')
            ->where('playlist.id', 'playlist-empty-items')
            ->where('playlist.sync_status.isRunning', true)
        );

    Queue::assertPushed(SyncPlaylistTracksJob::class, function (SyncPlaylistTracksJob $job) use ($user, $playlist): bool {
        return $job->userId === $user->id && $job->playlistId === $playlist->spotify_id;
    });
});
