<?php

namespace App\Http\Controllers\Insights;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshSpotifyInsightsJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

final class RefreshController extends Controller
{
    public function __invoke(
        Request $request,
    ): RedirectResponse {
        $user = $request->user();

        RefreshSpotifyInsightsJob::dispatch($user->id)->onQueue('insights');

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Insights refresh started. New data will appear shortly.'),
        ]);

        return back();
    }
}
