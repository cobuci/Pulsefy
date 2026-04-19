<?php

use App\Events\Library\LibraryPlaylistTracksSynced;
use App\Jobs\SyncPlaylistTracksJob;
use App\Models\Playlist;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Illuminate\Support\Facades\Event;

test('sync playlist tracks job syncs tracks and broadcasts completion event', function () {
    Event::fake([LibraryPlaylistTracksSynced::class]);

    $user = User::factory()->create();
    $playlist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-job-1',
    ]);

    $service = Mockery::mock(SpotifyLibraryService::class);
    $service->shouldReceive('syncPlaylistTracks')
        ->once()
        ->withArgs(function (User $modelUser, Playlist $modelPlaylist) use ($user, $playlist): bool {
            return $modelUser->id === $user->id && $modelPlaylist->id === $playlist->id;
        });
    app()->instance(SpotifyLibraryService::class, $service);

    $job = new SyncPlaylistTracksJob($user->id, $playlist->spotify_id);
    $job->handle($service, app(LibrarySyncStatusService::class));

    Event::assertDispatched(LibraryPlaylistTracksSynced::class, function (LibraryPlaylistTracksSynced $event) use ($user, $playlist): bool {
        return $event->userId === $user->id
            && $event->playlistId === $playlist->spotify_id
            && $event->hasFailure === false;
    });
});
