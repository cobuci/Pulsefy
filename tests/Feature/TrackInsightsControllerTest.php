<?php

use App\Enums\TrackInsightStatus;
use App\Jobs\GenerateTrackInsightsJob;
use App\Models\TrackInsight;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('POST track-insights queues generation job and returns 202', function () {
    Queue::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('player.track-insights'), [
            'track_id' => 'spotify_insights_ctrl_1',
            'track_name' => 'Hello',
            'artist_name' => 'Adele',
            'album_name' => '25',
        ])
        ->assertStatus(202)
        ->assertJson(['ok' => true, 'status' => 'queued']);

    $this->assertDatabaseHas('track_insights', [
        'track_id' => 'spotify_insights_ctrl_1',
        'status' => TrackInsightStatus::Queued->value,
    ]);

    Queue::assertPushed(GenerateTrackInsightsJob::class);
});

test('POST track-insights returns ready insight without re-queuing', function () {
    Queue::fake();

    $user = User::factory()->create();
    TrackInsight::factory()->ready()->create([
        'track_id' => 'spotify_insights_ctrl_ready',
        'track_name' => 'Hello',
        'artist_name' => 'Adele',
    ]);

    $this->actingAs($user)
        ->postJson(route('player.track-insights'), [
            'track_id' => 'spotify_insights_ctrl_ready',
            'track_name' => 'Hello',
            'artist_name' => 'Adele',
        ])
        ->assertOk()
        ->assertJson(['ok' => true, 'status' => 'ready'])
        ->assertJsonPath('insights.summary', 'A great song about life.')
        ->assertJsonPath('insights.summary_pt', 'Uma ótima música sobre a vida.');

    Queue::assertNothingPushed();
});

test('POST track-insights marks stale processing as failed and re-queues', function () {
    Queue::fake();

    $user = User::factory()->create();
    TrackInsight::factory()->processing()->create([
        'track_id' => 'spotify_insights_ctrl_stale',
        'track_name' => 'Hello',
        'artist_name' => 'Adele',
        'started_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.track-insights'), [
            'track_id' => 'spotify_insights_ctrl_stale',
            'track_name' => 'Hello',
            'artist_name' => 'Adele',
        ])
        ->assertStatus(202)
        ->assertJson(['ok' => true, 'status' => 'queued']);

    $this->assertDatabaseHas('track_insights', [
        'track_id' => 'spotify_insights_ctrl_stale',
        'status' => TrackInsightStatus::Queued->value,
    ]);

    Queue::assertPushed(GenerateTrackInsightsJob::class);
});

test('POST track-insights/regenerate forces re-generation even when ready', function () {
    Queue::fake();

    $user = User::factory()->create();
    TrackInsight::factory()->ready()->create([
        'track_id' => 'spotify_insights_ctrl_regen',
        'track_name' => 'Hello',
        'artist_name' => 'Adele',
    ]);

    $this->actingAs($user)
        ->postJson(route('player.track-insights.regenerate'), [
            'track_id' => 'spotify_insights_ctrl_regen',
            'track_name' => 'Hello',
            'artist_name' => 'Adele',
        ])
        ->assertStatus(202)
        ->assertJson(['ok' => true, 'status' => 'queued']);

    $this->assertDatabaseHas('track_insights', [
        'track_id' => 'spotify_insights_ctrl_regen',
        'status' => TrackInsightStatus::Queued->value,
    ]);

    Queue::assertPushed(GenerateTrackInsightsJob::class);
});

test('GET track-insights returns current status', function () {
    $user = User::factory()->create();
    TrackInsight::factory()->ready()->create([
        'track_id' => 'spotify_insights_ctrl_status',
        'track_name' => 'Hello',
        'artist_name' => 'Adele',
    ]);

    $this->actingAs($user)
        ->getJson(route('player.track-insights.status', ['track_id' => 'spotify_insights_ctrl_status']))
        ->assertOk()
        ->assertJson(['status' => 'ready'])
        ->assertJsonPath('insights.summary', 'A great song about life.')
        ->assertJsonPath('insights.summary_pt', 'Uma ótima música sobre a vida.');
});

test('GET track-insights returns null status when no record exists', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('player.track-insights.status', ['track_id' => 'spotify_insights_ctrl_none']))
        ->assertOk()
        ->assertJson(['status' => null, 'insights' => null]);
});
