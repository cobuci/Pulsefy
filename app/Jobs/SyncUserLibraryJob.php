<?php

namespace App\Jobs;

use App\Events\Library\LibrarySyncStatusUpdated;
use App\Events\Spotify\SpotifySyncFailed;
use App\Models\Playlist;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

final class SyncUserLibraryJob implements ShouldBeUnique, ShouldQueue
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
            (new WithoutOverlapping('library-sync:user:'.$this->userId))
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

    public function handle(SpotifyLibraryService $libraryService, LibrarySyncStatusService $statusService): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $hasFailure = false;

        $statusService->startUserSync($user->id, 1);
        LibrarySyncStatusUpdated::dispatch($user->id);

        try {
            $libraryService->syncUserPlaylists($user);

            $playlists = Playlist::query()
                ->whereBelongsTo($user)
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '<=', now());
                })
                ->orderBy('id')
                ->get(['id', 'user_id', 'spotify_id']);

            $total = max(1, $playlists->count());
            $completed = 0;
            $statusService->startUserSync($user->id, $total);
            LibrarySyncStatusUpdated::dispatch($user->id);

            foreach ($playlists as $playlist) {
                try {
                    $libraryService->syncPlaylistTracks($user, $playlist);
                } catch (\Throwable $exception) {
                    $hasFailure = true;

                    Log::channel('spotify')->warning('Library playlist track sync failed in job', [
                        'user_id' => $user->id,
                        'playlist_id' => $playlist->id,
                        'spotify_id' => $playlist->spotify_id,
                        'error' => $exception->getMessage(),
                    ]);
                }

                $completed++;
                $statusService->updateUserProgress($user->id, $completed, $total, $hasFailure);
                LibrarySyncStatusUpdated::dispatch($user->id);
            }
        } catch (\Throwable $exception) {
            $hasFailure = true;

            Log::channel('spotify')->warning('Library sync failed in job', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $statusService->finishUserSync($user->id, $hasFailure);
        LibrarySyncStatusUpdated::dispatch($user->id);
    }

    public function failed(\Throwable $exception): void
    {
        SpotifySyncFailed::dispatch(
            $this->userId,
            'Library sync failed. Your playlists may be outdated.',
        );
    }
}
