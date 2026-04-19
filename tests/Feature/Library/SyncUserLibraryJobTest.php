<?php

use App\Events\Library\LibrarySyncStatusUpdated;
use App\Jobs\SyncUserLibraryJob;
use App\Models\Playlist;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Illuminate\Support\Facades\Event;

test('sync user library job syncs playlists and stale playlist tracks while broadcasting progress', function () {
    Event::fake([LibrarySyncStatusUpdated::class]);

    $user = User::factory()->create();
    $stalePlaylist = Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-stale-job-1',
        'expires_at' => now()->subMinute(),
    ]);
    Playlist::factory()->create([
        'user_id' => $user->id,
        'spotify_id' => 'playlist-fresh-job-1',
        'expires_at' => now()->addMinutes(30),
    ]);

    $service = Mockery::mock(SpotifyLibraryService::class);
    $service->shouldReceive('syncUserPlaylists')->once()->andReturn(2);
    $service->shouldReceive('syncPlaylistTracks')
        ->once()
        ->withArgs(function (User $modelUser, Playlist $playlist) use ($user, $stalePlaylist): bool {
            return $modelUser->id === $user->id && $playlist->id === $stalePlaylist->id;
        });
    app()->instance(SpotifyLibraryService::class, $service);

    $job = new SyncUserLibraryJob($user->id);
    $job->handle($service, app(LibrarySyncStatusService::class));

    Event::assertDispatched(LibrarySyncStatusUpdated::class);
});
