<?php

use App\Ai\Agents\DiscoveryRecommendationAgent;
use App\Models\Track;
use App\Models\User;
use App\Services\Discovery\GeminiRecommendationResolver;
use Illuminate\Support\Facades\Http;

it('returns empty array when agent throws', function (): void {
    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => throw new RuntimeException('Gemini unavailable'));

    $resolver = app(GeminiRecommendationResolver::class);

    $result = $resolver->resolve(
        user: $user,
        affinityMap: ['Metallica' => 100.0],
        similarArtists: ['Iron Maiden' => 0.9],
        topTrackNames: ['Enter Sandman'],
        exclusionSet: [],
    );

    expect($result)->toBeArray()->toBeEmpty();
});

it('returns empty array when agent returns no tracks', function (): void {
    $user = User::factory()->create();

    DiscoveryRecommendationAgent::fake(fn () => ['tracks' => []]);

    Http::fake();

    $resolver = app(GeminiRecommendationResolver::class);

    $result = $resolver->resolve(
        user: $user,
        affinityMap: [],
        similarArtists: [],
        topTrackNames: [],
        exclusionSet: [],
    );

    expect($result)->toBeArray()->toBeEmpty();
    Http::assertNothingSent();
});

it('resolves tracks via spotify search and returns candidates', function (): void {
    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    DiscoveryRecommendationAgent::fake(fn () => [
        'tracks' => [
            ['track' => 'Hallowed Be Thy Name', 'artist' => 'Iron Maiden', 'reason' => 'Classic metal'],
        ],
    ]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'tracks' => [
                'items' => [
                    [
                        'id' => 'SPOTIFYID001',
                        'name' => 'Hallowed Be Thy Name',
                        'artists' => [['name' => 'Iron Maiden']],
                        'album' => [
                            'name' => 'The Number of the Beast',
                            'images' => [['url' => 'https://example.com/img.jpg']],
                        ],
                        'preview_url' => null,
                    ],
                ],
            ],
        ], 200),
    ]);

    $resolver = app(GeminiRecommendationResolver::class);

    $result = $resolver->resolve(
        user: $user,
        affinityMap: ['Metallica' => 100.0, 'Iron Maiden' => 60.0],
        similarArtists: ['Iron Maiden' => 0.9],
        topTrackNames: ['Enter Sandman'],
        exclusionSet: [],
    );

    expect($result)->toHaveKey('SPOTIFYID001');

    $track = Track::query()->where('spotify_id', 'SPOTIFYID001')->first();

    expect($track)->not->toBeNull()
        ->and($track->name)->toBe('Hallowed Be Thy Name')
        ->and($track->image_url)->toBe('https://example.com/img.jpg');

    expect($result['SPOTIFYID001']['display_artist'])->toBe('Iron Maiden');

    expect($result['SPOTIFYID001'])->toMatchArray([
        'track_id' => $track->id,
        'artist_affinity' => 60.0,
        'lastfm_match' => 90.0,
    ]);
});

it('excludes tracks present in the exclusion set', function (): void {
    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    DiscoveryRecommendationAgent::fake(fn () => [
        'tracks' => [
            ['track' => 'Hallowed Be Thy Name', 'artist' => 'Iron Maiden', 'reason' => 'Classic metal'],
        ],
    ]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'tracks' => [
                'items' => [
                    [
                        'id' => 'EXCLUDED001',
                        'name' => 'Hallowed Be Thy Name',
                        'artists' => [['name' => 'Iron Maiden']],
                        'album' => ['name' => 'The Number of the Beast', 'images' => []],
                        'preview_url' => null,
                    ],
                ],
            ],
        ], 200),
    ]);

    $resolver = app(GeminiRecommendationResolver::class);

    $result = $resolver->resolve(
        user: $user,
        affinityMap: [],
        similarArtists: [],
        topTrackNames: [],
        exclusionSet: ['EXCLUDED001' => true],
    );

    expect($result)->toBeEmpty();
});

it('skips tracks when spotify search returns no results', function (): void {
    $user = User::factory()->create([
        'spotify_token' => 'fake-token',
        'spotify_token_expires_at' => now()->addHour(),
    ]);

    DiscoveryRecommendationAgent::fake(fn () => [
        'tracks' => [
            ['track' => 'Unknown Track', 'artist' => 'Unknown Artist', 'reason' => 'test'],
        ],
    ]);

    Http::fake([
        'api.spotify.com/v1/search*' => Http::response([
            'tracks' => ['items' => []],
        ], 200),
    ]);

    $resolver = app(GeminiRecommendationResolver::class);

    $result = $resolver->resolve(
        user: $user,
        affinityMap: [],
        similarArtists: [],
        topTrackNames: [],
        exclusionSet: [],
    );

    expect($result)->toBeEmpty();
});
