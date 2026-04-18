<?php

use App\Models\SpotifySyncRun;
use App\Models\User;

test('status route returns current sync status summary for authenticated user', function () {
    $user = User::factory()->create();

    SpotifySyncRun::query()->create([
        'user_id' => $user->id,
        'type' => 'top_artists',
        'status' => 'completed',
        'started_at' => now()->subSeconds(30),
        'finished_at' => now()->subSeconds(10),
    ]);

    SpotifySyncRun::query()->create([
        'user_id' => $user->id,
        'type' => 'top_tracks',
        'status' => 'running',
        'started_at' => now()->subSeconds(20),
    ]);

    SpotifySyncRun::query()->create([
        'user_id' => $user->id,
        'type' => 'recent_plays',
        'status' => 'failed',
        'started_at' => now()->subSeconds(25),
        'finished_at' => now()->subSeconds(5),
        'error' => 'fail',
    ]);

    $this->actingAs($user)
        ->get(route('insights.status'))
        ->assertOk()
        ->assertJsonPath('status.isRunning', true)
        ->assertJsonPath('status.hasFailure', true)
        ->assertJsonPath('status.completed', 1)
        ->assertJsonPath('status.total', 3)
        ->assertJsonPath('status.progress', 33);
});

test('status route does not include sync runs from other users', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    SpotifySyncRun::query()->create([
        'user_id' => $other->id,
        'type' => 'top_tracks',
        'status' => 'completed',
        'started_at' => now()->subMinute(),
        'finished_at' => now()->subSeconds(30),
    ]);

    $this->actingAs($user)
        ->get(route('insights.status'))
        ->assertOk()
        ->assertJsonPath('status.completed', 0)
        ->assertJsonPath('status.progress', 0)
        ->assertJsonPath('status.isRunning', false);
});
