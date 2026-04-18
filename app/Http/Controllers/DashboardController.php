<?php

namespace App\Http\Controllers;

use App\Services\Spotify\Contracts\SpotifyStatsProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, SpotifyStatsProvider $spotify): Response
    {
        $user = $request->user();
        $timeRange = in_array($request->get('period'), ['short_term', 'medium_term', 'long_term'])
            ? $request->get('period')
            : 'medium_term';

        return Inertia::render('Dashboard', [
            'period' => $timeRange,
            'topTracks' => Inertia::defer(fn () => $spotify->topTracks($user, $timeRange)),
            'topArtists' => Inertia::defer(fn () => $spotify->topArtists($user, $timeRange)),
            'recentPlays' => Inertia::defer(fn () => $spotify->recentlyPlayedUnique($user)),
        ]);
    }
}
