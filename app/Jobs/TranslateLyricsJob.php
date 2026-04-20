<?php

namespace App\Jobs;

use App\Events\Lyrics\TranslationUpdated;
use App\Models\LyricTranslation;
use App\Services\Lyrics\LyricsTranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class TranslateLyricsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $translationId)
    {
        $this->onQueue('spotify-sync');
    }

    public function handle(LyricsTranslationService $translationService): void
    {
        $translation = LyricTranslation::query()
            ->with('lyric')
            ->find($this->translationId);

        if (! $translation || ! $translation->lyric) {
            return;
        }

        $translation->update([
            'status' => LyricTranslation::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $this->dispatchStatusUpdate($translation);

        try {
            $result = $translationService->translate(
                $translation->lyric->artist_name,
                $translation->lyric->track_name,
                $translation->lyric->synced_lyrics ?? $translation->lyric->plain_lyrics ?? '',
            );

            $translation->update([
                'status' => LyricTranslation::STATUS_READY,
                'translated_lines' => $result['lines'],
                'provider' => $result['provider'],
                'model' => $result['model'],
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $translation->refresh();
        } catch (Throwable $exception) {
            $translation->update([
                'status' => LyricTranslation::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $translation->refresh();

            $this->dispatchStatusUpdate($translation);

            throw $exception;
        }

        $this->dispatchStatusUpdate($translation);
    }

    private function dispatchStatusUpdate(LyricTranslation $translation): void
    {
        try {
            event(TranslationUpdated::fromTranslation($translation));
        } catch (Throwable $exception) {
            Log::warning('Failed to broadcast lyrics translation status update.', [
                'translation_id' => $translation->id,
                'user_id' => $translation->user_id,
                'track_id' => $translation->track_id,
                'status' => $translation->status,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
