<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Spotify\Sync\SpotifySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncUserTopTracksJob implements ShouldQueue
{
    use Queueable;

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

        $sync->syncTopTracks($user);
    }
}
