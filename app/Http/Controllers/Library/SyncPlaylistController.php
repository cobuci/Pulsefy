<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Jobs\SyncPlaylistTracksJob;
use App\Models\Playlist;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class SyncPlaylistController extends Controller
{
    public function __construct(
        private readonly LibrarySyncStatusService $statusService,
    ) {}

    public function __invoke(string $playlistId): RedirectResponse
    {
        $user = request()->user();

        $playlist = Playlist::query()
            ->whereBelongsTo($user)
            ->where('spotify_id', $playlistId)
            ->firstOrFail();

        $status = $this->statusService->playlistStatus($user->id, $playlist->spotify_id);

        if (! $status['isRunning']) {
            SyncPlaylistTracksJob::dispatch($user->id, $playlist->spotify_id)->onQueue('spotify-sync');
            $this->statusService->startPlaylistSync($user->id, $playlist->spotify_id);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Playlist sync started.'),
        ]);

        return back();
    }
}
