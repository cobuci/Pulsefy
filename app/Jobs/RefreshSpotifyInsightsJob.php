<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Spotify\Insights\SpotifyInsightsRefreshService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshSpotifyInsightsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $userId) {}

    /**
     * Execute the job.
     */
    public function handle(
        SpotifyInsightsRefreshService $refresh,
    ): void {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $refresh->refreshForUser($user);
    }
}
