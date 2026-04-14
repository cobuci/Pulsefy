<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RecentlyPlayedController extends Controller
{
    public function __invoke(Request $request, SpotifyService $spotify): Response
    {
        $user = $request->user();

        return Inertia::render('RecentlyPlayed', [
            'plays' => Inertia::defer(fn () => $spotify->recentlyPlayed($user)),
        ]);
    }
}
