<?php

namespace App\Jobs;

use App\Events\Library\LibraryPlaylistTracksSynced;
use App\Models\Playlist;
use App\Models\User;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use App\Services\Spotify\Library\SpotifyLibraryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

final class SyncPlaylistTracksJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $userId,
        public string $playlistId,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('library-sync:playlist:'.$this->userId.':'.$this->playlistId))
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

        $playlist = Playlist::query()
            ->whereBelongsTo($user)
            ->where('spotify_id', $this->playlistId)
            ->first();

        if (! $playlist) {
            return;
        }

        $statusService->startPlaylistSync($user->id, $playlist->spotify_id);
        $hasFailure = false;

        try {
            $libraryService->syncPlaylistTracks($user, $playlist);
        } catch (\Throwable $exception) {
            $hasFailure = true;

            Log::channel('spotify')->warning('Playlist track sync failed in job', [
                'user_id' => $user->id,
                'playlist_id' => $playlist->id,
                'spotify_id' => $playlist->spotify_id,
                'error' => $exception->getMessage(),
            ]);
        }

        $statusService->finishPlaylistSync($user->id, $playlist->spotify_id, $hasFailure);
        LibraryPlaylistTracksSynced::dispatch($user->id, $playlist->spotify_id, $hasFailure);
    }
}
