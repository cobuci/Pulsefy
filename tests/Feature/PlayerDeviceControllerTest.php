<?php

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('guests cannot access player device token and transfer endpoints', function () {
    $this->getJson(route('player.device-token'))->assertUnauthorized();
    $this->getJson(route('player.devices'))->assertUnauthorized();
    $this->postJson(route('player.transfer'), ['device_id' => 'abc'])->assertUnauthorized();
});

test('device token endpoint returns current access token', function () {
    $user = User::factory()->create([
        'spotify_token' => 'token-123',
        'spotify_token_expires_at' => Carbon::now()->addHour(),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.device-token'))
        ->assertOk()
        ->assertJson([
            'token' => 'token-123',
        ]);
});

test('device token endpoint returns 403 when token cannot be refreshed', function () {
    $user = User::factory()->create([
        'spotify_token_expires_at' => Carbon::now()->subMinutes(10),
    ]);

    Http::fake([
        'accounts.spotify.com/api/token*' => Http::failedConnection(),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.device-token'))
        ->assertForbidden()
        ->assertJson([
            'token' => null,
        ]);
});

test('transfer endpoint sends playback to provided device', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response(null, 204),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.transfer'), [
            'device_id' => 'device-xyz',
            'play' => true,
        ])
        ->assertOk()
        ->assertJson([
            'ok' => true,
        ]);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/v1/me/player')
            && $request['device_ids'] === ['device-xyz']
            && $request['play'] === true;
    });
});

test('transfer endpoint validates required device id', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('player.transfer'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_id']);
});

test('transfer endpoint returns 403 when spotify rejects command', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player*' => Http::response([
            'error' => ['status' => 403, 'message' => 'Premium required'],
        ], 403),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.transfer'), ['device_id' => 'device-xyz'])
        ->assertForbidden()
        ->assertJson([
            'ok' => false,
        ]);
});

test('devices endpoint returns available spotify connect devices', function () {
    $user = User::factory()->create();

    Http::fake([
        'api.spotify.com/v1/me/player/devices*' => Http::response([
            'devices' => [
                [
                    'id' => 'device-1',
                    'is_active' => true,
                    'is_private_session' => false,
                    'is_restricted' => false,
                    'name' => 'Pulsefy Web Player',
                    'type' => 'computer',
                    'volume_percent' => 66,
                    'supports_volume' => true,
                ],
            ],
        ]),
    ]);

    $this->actingAs($user)
        ->getJson(route('player.devices'))
        ->assertOk()
        ->assertJsonPath('devices.0.id', 'device-1')
        ->assertJsonPath('devices.0.is_active', true);
});
