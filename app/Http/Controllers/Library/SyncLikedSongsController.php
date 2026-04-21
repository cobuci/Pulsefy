<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Jobs\SyncLikedTracksJob;
use App\Services\Spotify\Library\LibrarySyncStatusService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class SyncLikedSongsController extends Controller
{
    public function __construct(
        private readonly LibrarySyncStatusService $statusService,
    ) {}

    public function __invoke(): RedirectResponse
    {
        $user = request()->user();

        $status = $this->statusService->playlistStatus($user->id, 'liked-songs');

        if (! $status['isRunning']) {
            SyncLikedTracksJob::dispatch($user->id)->onQueue('spotify-sync');
            $this->statusService->startPlaylistSync($user->id, 'liked-songs');
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Liked Songs sync started.'),
        ]);

        return back();
    }
}
