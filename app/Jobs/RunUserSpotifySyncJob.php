<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Spotify\Sync\SpotifySyncService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RunUserSpotifySyncJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct(public int $userId) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('spotify-sync:user:'.$this->userId))
                ->shared()
                ->releaseAfter(30)
                ->expireAfter(300),
        ];
    }

    public function uniqueId(): string
    {
        return (string) $this->userId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

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
