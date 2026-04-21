<?php

use App\Events\Library\LibraryPlaylistTracksSynced;
use App\Jobs\SyncLikedTracksJob;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Illuminate\Support\Facades\Event;

test('sync liked tracks job syncs tracks and broadcasts completion event', function () {
    Event::fake([LibraryPlaylistTracksSynced::class]);

    $user = User::factory()->create();

    $service = Mockery::mock(SpotifyLibraryService::class);
    $service->shouldReceive('syncLikedTracks')
        ->once()
        ->withArgs(function (User $modelUser) use ($user): bool {
            return $modelUser->id === $user->id;
        });
    app()->instance(SpotifyLibraryService::class, $service);

    $job = new SyncLikedTracksJob($user->id);
    $job->handle($service, app(LibrarySyncStatusService::class));

    Event::assertDispatched(LibraryPlaylistTracksSynced::class, function (LibraryPlaylistTracksSynced $event) use ($user): bool {
        return $event->userId === $user->id
            && $event->playlistId === 'liked-songs'
            && $event->hasFailure === false;
    });
});

test('sync liked tracks job marks failure when service throws', function () {
    Event::fake([LibraryPlaylistTracksSynced::class]);

    $user = User::factory()->create();

    $service = Mockery::mock(SpotifyLibraryService::class);
    $service->shouldReceive('syncLikedTracks')
        ->once()
        ->andThrow(new RuntimeException('Spotify error'));
    app()->instance(SpotifyLibraryService::class, $service);

    $job = new SyncLikedTracksJob($user->id);
    $job->handle($service, app(LibrarySyncStatusService::class));

    Event::assertDispatched(LibraryPlaylistTracksSynced::class, function (LibraryPlaylistTracksSynced $event) use ($user): bool {
        return $event->userId === $user->id
            && $event->playlistId === 'liked-songs'
            && $event->hasFailure === true;
    });
});

test('sync liked tracks job exits early when user not found', function () {
    Event::fake([LibraryPlaylistTracksSynced::class]);

    $service = Mockery::mock(SpotifyLibraryService::class);
    $service->shouldNotReceive('syncLikedTracks');
    app()->instance(SpotifyLibraryService::class, $service);

    $job = new SyncLikedTracksJob(999999);
    $job->handle($service, app(LibrarySyncStatusService::class));

    Event::assertNotDispatched(LibraryPlaylistTracksSynced::class);
});
