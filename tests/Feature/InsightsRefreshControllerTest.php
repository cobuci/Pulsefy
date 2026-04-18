<?php

use App\Jobs\RunUserSpotifySyncJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('refresh route dispatches spotify sync job on spotify-sync queue', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('insights.refresh'))
        ->assertRedirect();

    Queue::assertPushed(RunUserSpotifySyncJob::class, function (RunUserSpotifySyncJob $job) use ($user): bool {
        return $job->userId === $user->id && $job->queue === 'spotify-sync';
    });
});
