<?php

namespace App\Jobs;

use App\Events\Library\LibraryPlaylistTracksSynced;
use App\Events\Spotify\SpotifySyncFailed;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

final class SyncLikedTracksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $userId,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('library-sync:liked-songs:'.$this->userId))
                ->shared()
                ->releaseAfter(15)
                ->expireAfter(300),
        ];
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [5, 15, 60];
    }

    public function handle(SpotifyLibraryService $libraryService, LibrarySyncStatusService $statusService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $statusService->startPlaylistSync($user->id, 'liked-songs');
        $hasFailure = false;

        try {
            $libraryService->syncLikedTracks($user);
        } catch (\Throwable $exception) {
            $hasFailure = true;

            Log::channel('spotify')->warning('Liked tracks sync failed in job', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $statusService->finishPlaylistSync($user->id, 'liked-songs', $hasFailure);
        LibraryPlaylistTracksSynced::dispatch($user->id, 'liked-songs', $hasFailure);
    }

    public function failed(\Throwable $exception): void
    {
        SpotifySyncFailed::dispatch(
            $this->userId,
            'Liked songs sync failed. Your library may be outdated.',
        );
    }
}
