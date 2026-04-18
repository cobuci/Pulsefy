<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Spotify\Sync\SpotifySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunUserSpotifySyncJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public int $userId) {}

    /**
     * Execute the job.
     */
    public function handle(SpotifySyncService $sync): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $sync->syncTopArtists($user);
        $sync->syncTopTracks($user);
        $sync->syncRecentPlays($user);
    }
}
