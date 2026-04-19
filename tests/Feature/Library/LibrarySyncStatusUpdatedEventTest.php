<?php

use App\Events\Library\LibrarySyncStatusUpdated;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;

test('library sync status updated event broadcasts user sync status payload', function () {
    $user = User::factory()->create();

    app(LibrarySyncStatusService::class)->startUserSync($user->id, 5);
    app(LibrarySyncStatusService::class)->updateUserProgress($user->id, 2, 5);

    $event = new LibrarySyncStatusUpdated($user->id);
    $channel = $event->broadcastOn();

    expect($channel->name)->toBe('private-App.Models.User.'.$user->id)
        ->and($event->broadcastAs())->toBe('Library.SyncStatusUpdated')
        ->and(data_get($event->broadcastWith(), 'status.isRunning'))->toBeTrue()
        ->and(data_get($event->broadcastWith(), 'status.completed'))->toBe(2)
        ->and(data_get($event->broadcastWith(), 'status.total'))->toBe(5);
});
