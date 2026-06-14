<?php

use App\Ai\Agents\DiscoveryRecommendationAgent;
use App\Events\Discovery\DiscoveryRecommendationsUpdated;
use App\Jobs\GenerateDiscoveryRecommendationsJob;
use App\Models\User;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

test('generate discovery recommendations job broadcasts when generation completes', function () {
    Event::fake([DiscoveryRecommendationsUpdated::class]);

    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake();

    (new GenerateDiscoveryRecommendationsJob($user))->handle(app(DiscoveryService::class));

    Event::assertDispatched(
        DiscoveryRecommendationsUpdated::class,
        fn (DiscoveryRecommendationsUpdated $event): bool => $event->userId === $user->id,
    );
});

test('generate discovery recommendations job broadcasts when generation fails', function () {
    Event::fake([DiscoveryRecommendationsUpdated::class]);

    $user = User::factory()->create();

    (new GenerateDiscoveryRecommendationsJob($user))->failed(new RuntimeException('Gemini unavailable'));

    Event::assertDispatched(
        DiscoveryRecommendationsUpdated::class,
        fn (DiscoveryRecommendationsUpdated $event): bool => $event->userId === $user->id,
    );
});
