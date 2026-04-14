<?php

use App\Models\SpotifyStat;

test('isExpired returns true when expires_at is in the past', function () {
    $stat = SpotifyStat::factory()->make(['expires_at' => now()->subHour()]);

    expect($stat->isExpired())->toBeTrue();
});

test('isExpired returns false when expires_at is in the future', function () {
    $stat = SpotifyStat::factory()->make(['expires_at' => now()->addHour()]);

    expect($stat->isExpired())->toBeFalse();
});

test('expired factory state creates an expired stat', function () {
    $stat = SpotifyStat::factory()->expired()->make();

    expect($stat->isExpired())->toBeTrue();
});

test('payload is cast to array', function () {
    $stat = SpotifyStat::factory()->make([
        'payload' => [['id' => 'test']],
    ]);

    expect($stat->payload)->toBeArray()
        ->and($stat->payload[0]['id'])->toBe('test');
});
