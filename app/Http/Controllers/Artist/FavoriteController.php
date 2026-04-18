<?php

namespace App\Http\Controllers\Artist;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FavoriteController extends Controller
{
    public function __invoke(Request $request, SpotifyArtistProvider $artists, string $artistId): JsonResponse
    {
        $favorite = $request->boolean('favorite', true);

        $ok = $favorite
            ? $artists->followArtist($request->user(), $artistId)
            : $artists->unfollowArtist($request->user(), $artistId);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'favorite' => $artists->isArtistFollowed($request->user(), $artistId),
                'requires_reauth' => true,
                'message' => 'Missing Spotify permission. Reconnect Spotify and try again.',
            ]);
        }

        return response()->json([
            'ok' => true,
            'favorite' => $favorite,
        ]);
    }
}
