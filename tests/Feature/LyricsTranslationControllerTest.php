<?php

use App\Jobs\TranslateLyricsJob;
use App\Models\Lyric;
use App\Models\LyricTranslation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Http::preventStrayRequests();
});

test('translation endpoint queues translation job when lyrics exist', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => "[00:01.00] Hello from the other side\n[00:05.00] I must have called a thousand times",
            'plainLyrics' => "Hello from the other side\nI must have called a thousand times",
        ]], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('player.lyrics.translate'), [
            'track_id' => 'spotify_track_translate_1',
            'artist' => 'Adele',
            'track_name' => 'Hello',
            'album_name' => '25',
            'duration' => 295000,
        ])
        ->assertStatus(202)
        ->assertJson([
            'ok' => true,
            'track_id' => 'spotify_track_translate_1',
            'status' => LyricTranslation::STATUS_QUEUED,
        ]);

    $lyric = Lyric::query()->where('track_id', 'spotify_track_translate_1')->first();
    expect($lyric)->not->toBeNull();

    $translation = LyricTranslation::query()->where('user_id', $user->id)->where('track_id', 'spotify_track_translate_1')->first();

    expect($translation)->not->toBeNull();
    expect($translation?->status)->toBe(LyricTranslation::STATUS_QUEUED);

    Queue::assertPushed(TranslateLyricsJob::class, function (TranslateLyricsJob $job) use ($translation): bool {
        return $translation !== null
            && $job->translationId === $translation->id
            && $job->queue === 'spotify-sync';
    });
});

test('lyrics endpoint includes translation payload for current user', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => '[00:01.00] Hello from the other side',
            'plainLyrics' => 'Hello from the other side',
        ]], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('player.lyrics.translate'), [
        'track_id' => 'spotify_track_translate_2',
        'artist' => 'Adele',
        'track_name' => 'Hello',
        'album_name' => '25',
        'duration' => 295000,
    ])->assertStatus(202);

    LyricTranslation::query()->where('user_id', $user->id)->where('track_id', 'spotify_track_translate_2')->update([
        'status' => LyricTranslation::STATUS_READY,
        'translated_lines' => [[
            'index' => 0,
            'timestamp' => '00:01.00',
            'text' => 'Hello from the other side',
            'source_lang' => 'en',
            'pt_br' => 'Olá, do outro lado',
            'en' => null,
        ]],
        'provider' => 'gemini',
        'model' => 'gemini-3.1-flash-lite-preview',
        'error_message' => null,
    ]);

    $this->actingAs($user)
        ->getJson(route('player.lyrics', [
            'track_id' => 'spotify_track_translate_2',
            'artist' => 'Adele',
            'track_name' => 'Hello',
            'album_name' => '25',
            'duration' => 295000,
        ]))
        ->assertOk()
        ->assertJsonPath('translation.status', LyricTranslation::STATUS_READY)
        ->assertJsonPath('translation.provider', 'gemini')
        ->assertJsonPath('translation.model', 'gemini-3.1-flash-lite-preview')
        ->assertJsonPath('translation.translated_lines.0.timestamp', '00:01.00')
        ->assertJsonPath('translation.translated_lines.0.pt_br', 'Olá, do outro lado');
});

test('translation endpoint returns validation error when there are no lyrics to translate', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([], 200),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('player.lyrics.translate'), [
            'track_id' => 'spotify_track_translate_none',
            'artist' => 'Unknown Artist',
            'track_name' => 'Unknown Song',
        ])
        ->assertStatus(422)
        ->assertJson([
            'ok' => false,
            'message' => 'Lyrics are not available for translation.',
        ]);

    Queue::assertNothingPushed();
});

test('stale processing translation is marked failed before re-queuing', function () {
    Queue::fake();

    Http::fake([
        'lrclib.net/api/get*' => Http::response([], 404),
        'lrclib.net/api/search*' => Http::response([[
            'syncedLyrics' => '[00:01.00] Hello',
            'plainLyrics' => 'Hello',
        ]], 200),
    ]);

    $user = User::factory()->create();

    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_stale_processing',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => '[00:01.00] Hello',
        'plain_lyrics' => 'Hello',
        'is_synced' => true,
    ]);

    LyricTranslation::factory()->create([
        'user_id' => $user->id,
        'lyric_id' => $lyric->id,
        'track_id' => $lyric->track_id,
        'status' => LyricTranslation::STATUS_PROCESSING,
        'started_at' => now()->subMinutes(5),
    ]);

    $this->actingAs($user)
        ->postJson(route('player.lyrics.translate'), [
            'track_id' => 'spotify_track_stale_processing',
            'artist' => 'Adele',
            'track_name' => 'Hello',
        ])
        ->assertStatus(202)
        ->assertJson(['ok' => true, 'status' => LyricTranslation::STATUS_QUEUED]);

    $this->assertDatabaseHas('lyric_translations', [
        'user_id' => $user->id,
        'track_id' => 'spotify_track_stale_processing',
        'status' => LyricTranslation::STATUS_QUEUED,
    ]);

    Queue::assertPushed(TranslateLyricsJob::class);
});
