<?php

namespace App\Http\Controllers\Album;

use App\Http\Controllers\Controller;
use App\Services\Spotify\Contracts\SpotifyArtistProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class FavoriteController extends Controller
{
    public function __invoke(Request $request, SpotifyArtistProvider $artists, string $albumId): JsonResponse
    {
        $favorite = $request->boolean('favorite', true);

        $ok = $favorite
            ? $artists->saveAlbum($request->user(), $albumId)
            : $artists->unsaveAlbum($request->user(), $albumId);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'favorite' => $artists->isAlbumSaved($request->user(), $albumId),
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
