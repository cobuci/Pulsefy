<?php

use App\Events\Discovery\DiscoveryRecommendationsUpdated;
use App\Models\User;

test('discovery recommendations updated event broadcasts on private user channel', function () {
    $user = User::factory()->create();

    $event = new DiscoveryRecommendationsUpdated($user->id);

    expect($event->broadcastOn()->name)->toBe('private-App.Models.User.'.$user->id)
        ->and($event->broadcastAs())->toBe('Discovery.RecommendationsUpdated')
        ->and($event->broadcastWith())->toBe([]);
});
