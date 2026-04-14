<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlayerDevicesController extends Controller
{
    public function __construct(private readonly SpotifyDataService $spotify) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'devices' => $this->spotify->devices($request->user()),
        ]);
    }
}
