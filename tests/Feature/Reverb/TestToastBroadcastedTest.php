<?php

use App\Events\Reverb\TestToastBroadcasted;
use App\Models\User;

test('reverb test toast event broadcasts on user-scoped test channel', function () {
    $user = User::factory()->create();

    $event = new TestToastBroadcasted($user->id, 'hello');

    $channel = $event->broadcastOn();

    expect($channel->name)->toBe('reverb-test.'.$user->id)
        ->and($event->broadcastAs())->toBe('Reverb.TestToastBroadcasted')
        ->and(data_get($event->broadcastWith(), 'message'))->toBe('hello');
});
