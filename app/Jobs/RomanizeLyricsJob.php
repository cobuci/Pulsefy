<?php

namespace App\Jobs;

use App\Events\Lyrics\PronunciationUpdated;
use App\Models\LyricPronunciation;
use App\Services\Lyrics\LyricsPronunciationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RomanizeLyricsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $pronunciationId)
    {
        $this->onQueue('spotify-sync');
    }

    public function handle(LyricsPronunciationService $pronunciationService): void
    {
        $pronunciation = LyricPronunciation::query()
            ->with('lyric')
            ->find($this->pronunciationId);

        if (! $pronunciation || ! $pronunciation->lyric) {
            return;
        }

        $pronunciation->update([
            'status' => LyricPronunciation::STATUS_PROCESSING,
            'started_at' => now(),
            'error_message' => null,
        ]);

        $this->dispatchStatusUpdate($pronunciation);

        try {
            $result = $pronunciationService->romanize(
                $pronunciation->lyric->artist_name,
                $pronunciation->lyric->track_name,
                $pronunciation->lyric->synced_lyrics ?? $pronunciation->lyric->plain_lyrics ?? '',
            );

            $pronunciation->update([
                'status' => LyricPronunciation::STATUS_READY,
                'romanized_lines' => $result['lines'],
                'provider' => $result['provider'],
                'model' => $result['model'],
                'completed_at' => now(),
                'error_message' => null,
            ]);

            $pronunciation->refresh();
        } catch (Throwable $exception) {
            $pronunciation->update([
                'status' => LyricPronunciation::STATUS_FAILED,
                'completed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $pronunciation->refresh();

            $this->dispatchStatusUpdate($pronunciation);

            throw $exception;
        }

        $this->dispatchStatusUpdate($pronunciation);
    }

    private function dispatchStatusUpdate(LyricPronunciation $pronunciation): void
    {
        try {
            event(PronunciationUpdated::fromPronunciation($pronunciation));
        } catch (Throwable $exception) {
            Log::warning('Failed to broadcast lyrics pronunciation status update.', [
                'pronunciation_id' => $pronunciation->id,
                'user_id' => $pronunciation->user_id,
                'track_id' => $pronunciation->track_id,
                'status' => $pronunciation->status,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
