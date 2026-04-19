<?php

use App\Jobs\BackfillArtistGenresJob;
use App\Jobs\RunUserSpotifySyncJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

test('jobs settings page is available for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('jobs.edit'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('settings/Jobs')
        );
});

test('jobs dispatch route enqueues artist genre backfill job', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jobs.dispatch'), [
            'job' => 'backfill_artist_genres',
        ])
        ->assertRedirect();

    Queue::assertPushed(
        BackfillArtistGenresJob::class,
        fn (BackfillArtistGenresJob $job): bool => $job->queue === 'spotify-sync',
    );
});

test('jobs dispatch route enqueues current user spotify sync job', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('jobs.dispatch'), [
            'job' => 'sync_user_spotify',
        ])
        ->assertRedirect();

    Queue::assertPushed(
        RunUserSpotifySyncJob::class,
        fn (RunUserSpotifySyncJob $job): bool => $job->userId === $user->id && $job->queue === 'spotify-sync',
    );
});
