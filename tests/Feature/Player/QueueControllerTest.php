<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests cannot access player queue endpoint', function () {
    $this->getJson(route('player.queue'))
        ->assertUnauthorized();
});

test('returns next track from spotify queue', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/queue' => Http::response([
            'queue' => [
                [
                    'id' => 'next-track-1',
                    'name' => 'Next Song',
                    'artists' => [['id' => 'artist-1', 'name' => 'Next Artist']],
                    'album' => ['images' => [['url' => 'https://example.com/cover.jpg']]],
                    'duration_ms' => 200000,
                    'external_urls' => ['spotify' => 'https://open.spotify.com/track/next-track-1'],
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.queue'))
        ->assertOk()
        ->assertJsonPath('next_track.id', 'next-track-1')
        ->assertJsonPath('next_track.name', 'Next Song')
        ->assertJsonPath('next_track.artists.0.name', 'Next Artist');
});

test('returns null next track when spotify queue is empty', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/queue' => Http::response([
            'queue' => [],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.queue'))
        ->assertOk()
        ->assertJsonPath('next_track', null);
});

test('returns null next track when spotify responds with 401', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/queue' => Http::response(
            ['error' => ['status' => 401, 'message' => 'No token']],
            401,
        ),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.queue'))
        ->assertOk()
        ->assertJsonPath('next_track', null);
});
