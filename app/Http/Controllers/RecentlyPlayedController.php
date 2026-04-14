<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RecentlyPlayedController extends Controller
{
    public function __invoke(Request $request, SpotifyDataService $spotify): Response
    {
        $user = $request->user();

        return Inertia::render('RecentlyPlayed', [
            'plays' => Inertia::defer(fn () => $spotify->recentlyPlayed($user)),
        ]);
    }
}
