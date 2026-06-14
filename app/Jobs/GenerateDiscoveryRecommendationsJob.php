<?php

namespace App\Jobs;

use App\Enums\DailyRecommendationStatus;
use App\Models\DailyRecommendation;
use App\Models\User;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GenerateDiscoveryRecommendationsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly User $user,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->user->id;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(DiscoveryService $discovery): void
    {
        $daily = DailyRecommendation::query()
            ->where('user_id', $this->user->id)
            ->whereDate('date', now()->startOfDay())
            ->first();

        if ($daily !== null) {
            $daily->update([
                'status' => DailyRecommendationStatus::Processing,
                'started_at' => now(),
                'error_message' => null,
            ]);
        }

        $discovery->generate($this->user);
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception?->getMessage() ?? 'Recommendation generation failed. Please try again.';

        Log::channel('stack')->warning('Discovery recommendation generation failed', [
            'user_id' => $this->user->id,
            'error' => $message,
        ]);

        $daily = DailyRecommendation::query()
            ->where('user_id', $this->user->id)
            ->whereDate('date', now()->startOfDay())
            ->first();

        $daily?->markFailed($message);
    }
}
