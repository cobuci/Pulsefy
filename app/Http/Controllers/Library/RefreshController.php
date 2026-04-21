<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Jobs\SyncLikedTracksJob;
use App\Jobs\SyncUserLibraryJob;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class RefreshController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $user = request()->user();

        SyncUserLibraryJob::dispatch($user->id)->onQueue('spotify-sync');
        SyncLikedTracksJob::dispatch($user->id)->onQueue('spotify-sync');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Library sync started. Playlists will update shortly.'),
        ]);

        return back();
    }
}
