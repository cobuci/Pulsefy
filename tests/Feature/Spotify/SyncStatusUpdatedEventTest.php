<?php

use App\Events\Spotify\SyncStatusUpdated;
use App\Models\SpotifySyncRun;
use App\Models\User;

test('sync status updated event broadcasts on private user channel with status payload', function () {
    $user = User::factory()->create();

    SpotifySyncRun::query()->create([
        'user_id' => $user->id,
        'type' => 'top_artists',
        'status' => 'completed',
        'started_at' => now()->subMinute(),
        'finished_at' => now()->subSeconds(10),
    ]);

    $event = new SyncStatusUpdated($user->id);

    $channels = $event->broadcastOn();

    expect($channels->name)->toBe('private-App.Models.User.'.$user->id)
        ->and($event->broadcastAs())->toBe('Spotify.SyncStatusUpdated')
        ->and(data_get($event->broadcastWith(), 'status.completed'))->toBe(1)
        ->and(data_get($event->broadcastWith(), 'status.total'))->toBe(3);
});
