<?php

use App\Ai\Agents\DiscoveryRecommendationAgent;
use App\Models\DiscoveryLikedTrack;
use App\Models\TrackInteraction;
use App\Models\User;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('returns empty array when user has no top artists or recent plays', function (): void {
    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake();

    $result = app(DiscoveryService::class)->generate($user);

    expect($result)->toBeArray()->toBeEmpty();
});

it('excludes tracks with active skip suppression', function (): void {
    $user = User::factory()->create();
    $suppressedSpotifyId = 'AAAAAAAAAAAAAAAAAAAAAA';

    TrackInteraction::factory()->skip()->create([
        'user_id' => $user->id,
        'spotify_id' => $suppressedSpotifyId,
    ]);

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['similarartists' => ['artist' => []]], 200),
    ]);

    $result = app(DiscoveryService::class)->generate($user);

    $returnedIds = array_column($result, 'spotify_id');
    expect($returnedIds)->not->toContain($suppressedSpotifyId);
});

it('does not exclude liked tracks from recommendations', function (): void {
    $user = User::factory()->create();
    $likedSpotifyId = 'BBBBBBBBBBBBBBBBBBBBBB';

    TrackInteraction::factory()->like()->create([
        'user_id' => $user->id,
        'spotify_id' => $likedSpotifyId,
    ]);

    $suppressed = TrackInteraction::query()->suppressedForUser($user->id)->pluck('spotify_id');
    expect($suppressed)->not->toContain($likedSpotifyId);
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
            'spotify_id' => 'CCCCCCCCCCCCCCCCCCCCCC',
            'name' => 'Cached Track',
            'artist' => 'Some Artist',
            'album' => 'Some Album',
            'image_url' => null,
            'match_score' => 75,
            'preview_url' => null,
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

    DiscoveryLikedTrack::factory()->create([
        'user_id' => $user->id,
        'artist_name' => 'Liked Artist',
    ]);

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['similarartists' => ['artist' => []]], 200),
    ]);

    $result = app(DiscoveryService::class)->generate($user);

    expect($result)->toBeArray();
});
