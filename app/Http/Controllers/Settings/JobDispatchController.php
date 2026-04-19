<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\BackfillArtistGenresJob;
use App\Jobs\RunUserSpotifySyncJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class JobDispatchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $job = $request->string('job')->toString();

        if ($job === 'backfill_artist_genres') {
            BackfillArtistGenresJob::dispatch()->onQueue('spotify-sync');

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Artist genre backfill job dispatched.'),
            ]);

            return back();
        }

        if ($job === 'sync_user_spotify') {
            RunUserSpotifySyncJob::dispatch($user->id)->onQueue('spotify-sync');

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('User Spotify sync job dispatched.'),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Unknown job action.'),
        ]);

        return back();
    }
}
