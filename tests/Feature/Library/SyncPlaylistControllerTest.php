<?php

use App\Jobs\SyncPlaylistTracksJob;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('sync playlist endpoint dispatches playlist track sync job', function () {
    Queue::fake();

    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-manual-sync-1',
    ]);

    $this->actingAs($user)
        ->from(route('library.show', ['playlistId' => $playlist->spotify_id]))
        ->post(route('library.sync-playlist', ['playlistId' => $playlist->spotify_id]))
        ->assertRedirect(route('library.show', ['playlistId' => $playlist->spotify_id]));

    Queue::assertPushed(SyncPlaylistTracksJob::class, function (SyncPlaylistTracksJob $job) use ($user, $playlist): bool {
        return $job->userId === $user->id
            && $job->playlistId === $playlist->spotify_id
            && $job->queue === 'spotify-sync';
    });
});

test('sync playlist endpoint is protected by auth middleware', function () {
    $this->post(route('library.sync-playlist', ['playlistId' => 'playlist-1']))
        ->assertRedirect(route('login'));
});

test('users cannot sync playlists they do not own', function () {
    Queue::fake();

    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $otherUser->id,
        'spotify_id' => 'playlist-manual-sync-2',
    ]);

    $this->actingAs($user)
        ->post(route('library.sync-playlist', ['playlistId' => $playlist->spotify_id]))
        ->assertNotFound();

    Queue::assertNothingPushed();
});
