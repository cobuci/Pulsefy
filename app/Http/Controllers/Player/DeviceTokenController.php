<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Player\Concerns\RespondsWithPlayerJson;
use App\Services\Spotify\SpotifyTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeviceTokenController extends Controller
{
    use RespondsWithPlayerJson;

    public function __construct(private readonly SpotifyTokenService $tokenService) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $token = $this->tokenService->ensureFreshToken($request->user());
        } catch (\Throwable) {
            return $this->respondPayload([
                'token' => null,
            ], 403);
        }

        return $this->respondPayload([
            'token' => $token,
        ]);
    }
}
