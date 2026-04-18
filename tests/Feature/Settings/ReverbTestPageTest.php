<?php

use App\Jobs\DispatchReverbTestToastJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

test('reverb test page is available in settings for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reverb-test.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/ReverbTest')
            ->where('userId', $user->id)
            ->where('dispatchToastUrl', route('reverb-test.dispatch-toast'))
        );
});

test('dispatch toast route enqueues reverb test job', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('reverb-test.dispatch-toast'))
        ->assertNoContent();

    Queue::assertPushed(
        DispatchReverbTestToastJob::class,
        fn (DispatchReverbTestToastJob $job): bool => $job->userId === $user->id && $job->queue === 'spotify-sync',
    );
});
