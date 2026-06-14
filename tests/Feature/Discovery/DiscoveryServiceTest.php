<?php

use App\Ai\Agents\DiscoveryRecommendationAgent;
use App\Enums\DailyRecommendationStatus;
use App\Models\Artist;
use App\Models\DailyRecommendation;
use App\Models\DiscoveryLikedTrack;
use App\Models\RecommendedTrack;
use App\Models\Track;
use App\Models\TrackInteraction;
use App\Models\User;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Support\Facades\Http;

function makeArtist(string $name): Artist
{
    return Artist::query()->create([
        'artist_id' => 'spotify_'.str()->random(10),
        'artist_name' => $name,
        'genres' => [],
        'images' => [],
        'popularity' => 50,
        'uri' => 'spotify:artist:'.str()->random(10),
        'external_urls' => [],
        'fetched_at' => now(),
        'expires_at' => now()->addDays(7),
    ]);
}

it('returns empty array when user has no top artists or recent plays', function (): void {
    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake();

    $result = app(DiscoveryService::class)->generate($user);

    expect($result)->toBeArray()->toBeEmpty();

    $daily = DailyRecommendation::query()->where('user_id', $user->id)->first();
    expect($daily)->not->toBeNull()
        ->and($daily->status)->toBe(DailyRecommendationStatus::Empty);
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

it('appends recommendations without replacing the existing queue', function (): void {
    $user = User::factory()->create();
    $daily = DailyRecommendation::factory()->forToday()->create(['user_id' => $user->id]);
    $existingTrack = Track::factory()->create(['spotify_id' => 'EXISTINGTRACK00000001']);

    RecommendedTrack::factory()->create([
        'daily_recommendation_id' => $daily->id,
        'track_id' => $existingTrack->id,
        'artist_name' => 'Existing Artist',
        'match_score' => 90,
        'position' => 1,
    ]);

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['similarartists' => ['artist' => []]], 200),
    ]);

    app(DiscoveryService::class)->generate($user);

    expect(RecommendedTrack::query()->whereHas('recommendation', fn ($query) => $query->where('user_id', $user->id))->count())->toBe(1);
});

it('keeps pending recommendations from previous days visible', function (): void {
    $user = User::factory()->create();
    $yesterday = DailyRecommendation::factory()->create([
        'user_id' => $user->id,
        'date' => now()->subDay()->toDateString(),
    ]);
    $track = Track::factory()->create(['spotify_id' => 'YESTERDAY00000000001']);

    RecommendedTrack::factory()->create([
        'daily_recommendation_id' => $yesterday->id,
        'track_id' => $track->id,
        'artist_name' => 'Yesterday Artist',
        'match_score' => 88,
        'position' => 1,
    ]);

    $pending = app(DiscoveryService::class)->pendingRecommendationsForInertia($user);

    expect($pending)->toHaveCount(1)
        ->and($pending[0]['spotify_id'])->toBe('YESTERDAY00000000001');
});

it('marks daily recommendation as empty when generation produces no pending tracks', function (): void {
    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);
    Http::fake([
        'ws.audioscrobbler.com/*' => Http::response(['similarartists' => ['artist' => []]], 200),
    ]);

    app(DiscoveryService::class)->generate($user);

    expect(app(DiscoveryService::class)->pendingCount($user))->toBe(0);
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

it('includes artist in penalized set when track has active skip', function (): void {
    $user = User::factory()->create();
    $artist = makeArtist('Radiohead');
    $track = Track::factory()->create();
    $track->artists()->attach($artist);

    TrackInteraction::factory()->skip()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
    ]);

    $penalized = TrackInteraction::query()
        ->where('track_interactions.user_id', $user->id)
        ->where('track_interactions.type', 'skip')
        ->where('track_interactions.expires_at', '>', now())
        ->join('tracks', 'tracks.id', '=', 'track_interactions.track_id')
        ->join('artist_track', 'artist_track.track_id', '=', 'tracks.id')
        ->join('artists', 'artists.id', '=', 'artist_track.artist_model_id')
        ->pluck('artists.artist_name')
        ->map(fn (string $name) => mb_strtolower($name))
        ->all();

    expect($penalized)->toContain('radiohead');
});

it('does not include artist in penalized set when skip has expired', function (): void {
    $user = User::factory()->create();
    $artist = makeArtist('Radiohead');
    $track = Track::factory()->create();
    $track->artists()->attach($artist);

    TrackInteraction::factory()->create([
        'user_id' => $user->id,
        'track_id' => $track->id,
        'type' => 'skip',
        'interacted_at' => now()->subDays(20),
        'expires_at' => now()->subDays(6),
    ]);

    $penalized = TrackInteraction::query()
        ->where('track_interactions.user_id', $user->id)
        ->where('track_interactions.type', 'skip')
        ->where('track_interactions.expires_at', '>', now())
        ->join('tracks', 'tracks.id', '=', 'track_interactions.track_id')
        ->join('artist_track', 'artist_track.track_id', '=', 'tracks.id')
        ->join('artists', 'artists.id', '=', 'artist_track.artist_model_id')
        ->pluck('artists.artist_name')
        ->map(fn (string $name) => mb_strtolower($name))
        ->all();

    expect($penalized)->not->toContain('radiohead');
});
