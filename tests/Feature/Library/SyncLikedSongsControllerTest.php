<?php

use App\Jobs\SyncLikedTracksJob;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use Illuminate\Support\Facades\Queue;

test('sync liked songs endpoint dispatches liked tracks sync job', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('library.index'))
        ->post(route('library.liked-songs.sync'))
        ->assertRedirect(route('library.index'));

    Queue::assertPushed(SyncLikedTracksJob::class, function (SyncLikedTracksJob $job) use ($user): bool {
        return $job->userId === $user->id
            && $job->queue === 'spotify-sync';
    });
});

test('sync liked songs endpoint is protected by auth middleware', function () {
    $this->post(route('library.liked-songs.sync'))
        ->assertRedirect(route('login'));
});

test('sync liked songs endpoint does not dispatch job if already running', function () {
    Queue::fake();

    $user = User::factory()->create();

    $statusService = app(LibrarySyncStatusService::class);
    $statusService->startPlaylistSync($user->id, 'liked-songs');

    $this->actingAs($user)
        ->from(route('library.index'))
        ->post(route('library.liked-songs.sync'))
        ->assertRedirect(route('library.index'));

    Queue::assertNothingPushed();
});
