<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlayerDeviceController extends Controller
{
    public function __construct(private readonly SpotifyTokenService $tokenService) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $token = $this->tokenService->ensureFreshToken($request->user());
        } catch (\Throwable) {
            return response()->json([
                'token' => null,
            ], 403);
        }

        return response()->json([
            'token' => $token,
        ]);
    }
}
