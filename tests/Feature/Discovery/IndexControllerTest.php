<?php

use App\Jobs\GenerateDiscoveryRecommendationsJob;
use App\Models\DailyRecommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

test('guests are redirected to login', function () {
    $this->get(route('discovery.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users see the discovery page', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('discovery.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Discovery/Index')
        );
});

test('dispatches job and returns generating status when no recommendations exist for today', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('discovery.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Discovery/Index')
            ->where('status', 'generating')
            ->where('recommendations', [])
        );

    Queue::assertPushed(GenerateDiscoveryRecommendationsJob::class, fn ($job) => $job->user->id === $user->id);
});

test('returns ready status with recommendations when they exist for today', function () {
    Queue::fake();
    $user = User::factory()->create();

    $daily = DailyRecommendation::factory()->forToday()->create(['user_id' => $user->id]);

    $daily->tracks()->create([
        'spotify_id' => 'TRACK001',
        'name' => 'Test Track',
        'artist_name' => 'Test Artist',
        'album_name' => 'Test Album',
        'image_url' => null,
        'preview_url' => null,
        'match_score' => 80,
        'position' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('discovery.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Discovery/Index')
            ->where('status', 'ready')
            ->has('recommendations', 1)
            ->where('recommendations.0.spotify_id', 'TRACK001')
        );

    Queue::assertNotPushed(GenerateDiscoveryRecommendationsJob::class);
});
