<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyStatsProvider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __invoke(Request $request, SpotifyStatsProvider $spotify): Response
    {
        $user = $request->user();

        return Inertia::render('Artist/Index', [
            'topArtists' => Inertia::defer(fn () => $spotify->topArtists($user)),
        ]);
    }
}
