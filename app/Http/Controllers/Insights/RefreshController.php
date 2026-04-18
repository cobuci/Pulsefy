<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Jobs\RunUserSpotifySyncJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class RefreshController extends Controller
{
    public function __invoke(
        Request $request,
    ): RedirectResponse {
        $user = $request->user();

        RunUserSpotifySyncJob::dispatch($user->id)->onQueue('spotify-sync');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Sync started. Data will refresh shortly.'),
        ]);

        return back();
    }
}
