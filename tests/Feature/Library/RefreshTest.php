<?php

use App\Jobs\SyncUserLibraryJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('refresh endpoint triggers playlist sync and redirects back', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('library.index'))
        ->post(route('library.refresh'))
        ->assertRedirect(route('library.index'));

    Queue::assertPushed(SyncUserLibraryJob::class, function (SyncUserLibraryJob $job) use ($user): bool {
        return $job->userId === $user->id && $job->queue === 'spotify-sync';
    });
});

test('refresh endpoint is protected by auth middleware', function () {
    $this->post(route('library.refresh'))
        ->assertRedirect(route('login'));
});

test('refresh endpoint can be called repeatedly without creating duplicate unique jobs', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('library.index'))
        ->post(route('library.refresh'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('library.index'));

    $this->actingAs($user)
        ->from(route('library.index'))
        ->post(route('library.refresh'))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('library.index'));

    Queue::assertPushed(SyncUserLibraryJob::class, 1);
});
