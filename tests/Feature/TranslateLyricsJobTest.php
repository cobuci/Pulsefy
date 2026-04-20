<?php

use App\Ai\Agents\LyricsTranslationAgent;
use App\Events\Lyrics\TranslationUpdated;
use App\Jobs\TranslateLyricsJob;
use App\Models\Lyric;
use App\Models\LyricTranslation;
use App\Models\User;
use App\Services\Lyrics\LyricsTranslationService;
use Illuminate\Support\Facades\Event;

test('translate lyrics job stores translated lines and broadcasts update', function () {
    Event::fake([TranslationUpdated::class]);

    $user = User::factory()->create();
    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_job_1',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => "[00:01.00] Hello from the other side\n[00:05.00] I must have called a thousand times",
        'is_synced' => true,
    ]);

    $translation = LyricTranslation::factory()->create([
        'user_id' => $user->id,
        'lyric_id' => $lyric->id,
        'track_id' => $lyric->track_id,
        'status' => LyricTranslation::STATUS_QUEUED,
    ]);

    LyricsTranslationAgent::fake([
        [
            'lines' => [
                [
                    'index' => 0,
                    'timestamp' => '00:01.00',
                    'source_lang' => 'en',
                    'pt_br' => 'Olá, do outro lado',
                    'en' => null,
                ],
                [
                    'index' => 1,
                    'timestamp' => '00:05.00',
                    'source_lang' => 'en',
                    'pt_br' => 'Devo ter ligado mil vezes',
                    'en' => null,
                ],
            ],
        ],
    ]);

    (new TranslateLyricsJob($translation->id))->handle(app(LyricsTranslationService::class));

    $translation->refresh();

    expect($translation->status)->toBe(LyricTranslation::STATUS_READY);
    expect($translation->provider)->toBe('gemini');
    expect($translation->model)->toBe('gemini-3.1-flash-lite-preview');
    expect($translation->translated_lines)->toBeArray();
    expect($translation->translated_lines[0]['timestamp'] ?? null)->toBe('00:01.00');

    LyricsTranslationAgent::assertPrompted(fn () => true);

    Event::assertDispatched(TranslationUpdated::class, function (TranslationUpdated $event) use ($user, $lyric): bool {
        return $event->userId === $user->id
            && $event->trackId === $lyric->track_id
            && $event->status === LyricTranslation::STATUS_READY;
    });
});

test('translate lyrics job marks translation as failed when ai translation throws', function () {
    Event::fake([TranslationUpdated::class]);

    $user = User::factory()->create();
    $lyric = Lyric::factory()->create([
        'track_id' => 'spotify_track_job_fail_1',
        'artist_name' => 'Adele',
        'track_name' => 'Hello',
        'synced_lyrics' => "[00:01.00] Hello from the other side\n[00:05.00] I must have called a thousand times",
        'is_synced' => true,
    ]);

    $translation = LyricTranslation::factory()->create([
        'user_id' => $user->id,
        'lyric_id' => $lyric->id,
        'track_id' => $lyric->track_id,
        'status' => LyricTranslation::STATUS_QUEUED,
    ]);

    LyricsTranslationAgent::fake(function () {
        throw new RuntimeException('Gemini transport failure');
    });

    expect(fn () => (new TranslateLyricsJob($translation->id))->handle(app(LyricsTranslationService::class)))
        ->toThrow(RuntimeException::class);

    $translation->refresh();

    expect($translation->status)->toBe(LyricTranslation::STATUS_FAILED);
    expect($translation->error_message)->toContain('Gemini transport failure');

    Event::assertDispatched(TranslationUpdated::class, function (TranslationUpdated $event) use ($user, $lyric): bool {
        return $event->userId === $user->id
            && $event->trackId === $lyric->track_id
            && $event->status === LyricTranslation::STATUS_FAILED;
    });
});
