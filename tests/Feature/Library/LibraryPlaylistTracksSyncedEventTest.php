<?php

use App\Events\Library\LibraryPlaylistTracksSynced;
use App\Models\User;

test('library playlist tracks synced event broadcasts playlist payload', function () {
    $user = User::factory()->create();

    $event = new LibraryPlaylistTracksSynced($user->id, 'playlist-123', false);
    $channel = $event->broadcastOn();

    expect($channel->name)->toBe('private-App.Models.User.'.$user->id)
        ->and($event->broadcastAs())->toBe('Library.PlaylistTracksSynced')
        ->and(data_get($event->broadcastWith(), 'playlistId'))->toBe('playlist-123')
        ->and(data_get($event->broadcastWith(), 'hasFailure'))->toBeFalse();
});
