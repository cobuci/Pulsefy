<?php

use App\Ai\Agents\DiscoveryRecommendationAgent;
use App\Models\DiscoveryLikedTrack;
use App\Models\Track;
use App\Models\TrackInteraction;
use App\Models\User;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('returns empty array when user has no top artists or recent plays', function (): void {
    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake();

    $result = app(DiscoveryService::class)->generate($user);

    expect($result)->toBeArray()->toBeEmpty();
});

it('excludes tracks with active skip suppression', function (): void {
    $user = User::factory()->create();
    $track = Track::factory()->create(['spotify_id' => 'AAAAAAAAAAAAAAAAAAAAAA']);

    TrackInteraction::factory()->skip()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
    ]);

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['similarartists' => ['artist' => []]], 200),
    ]);

    $result = app(DiscoveryService::class)->generate($user);

    $returnedIds = array_column($result, 'spotify_id');
    expect($returnedIds)->not->toContain('AAAAAAAAAAAAAAAAAAAAAA');
});

it('does not exclude liked tracks from recommendations', function (): void {
    $user = User::factory()->create();
    $track = Track::factory()->create(['spotify_id' => 'BBBBBBBBBBBBBBBBBBBBBB']);

    TrackInteraction::factory()->like()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
    ]);

    $suppressed = TrackInteraction::query()->suppressedForUser($user->id)->pluck('track_id');
    expect($suppressed)->not->toContain($track->id);
});

it('proceeds gracefully when last.fm returns failure', function (): void {
    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['error' => 6, 'message' => 'Artist not found'], 200),
    ]);

    $result = app(DiscoveryService::class)->generate($user);

    expect($result)->toBeArray();
});

it('returns cached recommendations on second call without regenerating', function (): void {
    $user = User::factory()->create();
    $cacheKey = "discovery:{$user->id}:".now()->toDateString();

    $cached = [
        [
            'track_id' => 1,
            'match_score' => 75,
        ],
    ];
    Cache::put($cacheKey, $cached, 3600);

    DiscoveryRecommendationAgent::fake()->preventStrayPrompts();
    Http::fake();

    $result = app(DiscoveryService::class)->generate($user);

    Http::assertNothingSent();
    DiscoveryRecommendationAgent::assertNeverPrompted();
    expect($result)->toBe($cached);
});

it('sets cache after generating recommendations', function (): void {
    $user = User::factory()->create();
    $cacheKey = "discovery:{$user->id}:".now()->toDateString();

    Cache::forget($cacheKey);

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['similarartists' => ['artist' => []]], 200),
    ]);

    app(DiscoveryService::class)->generate($user);

    expect(Cache::has($cacheKey))->toBeTrue();
});

it('boosts affinity for artists of previously liked tracks', function (): void {
    $user = User::factory()->create();
    $track = Track::factory()->create();

    DiscoveryLikedTrack::factory()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
    ]);

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['similarartists' => ['artist' => []]], 200),
    ]);

    $result = app(DiscoveryService::class)->generate($user);

    expect($result)->toBeArray();
});
