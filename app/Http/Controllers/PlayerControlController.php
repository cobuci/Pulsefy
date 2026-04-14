<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlayerControlController extends Controller
{
    public function __construct(private readonly SpotifyService $spotify) {}

    public function play(Request $request): JsonResponse
    {
        $uri = $request->string('uri')->toString();

        $success = $uri === ''
            ? $this->spotify->resumePlay($request->user())
            : $this->spotify->play($request->user(), $uri);

        return $this->respond($success);
    }

    public function pause(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->pause($request->user()));
    }

    public function next(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->next($request->user()));
    }

    public function previous(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->previous($request->user()));
    }

    private function respond(bool $success): JsonResponse
    {
        return response()->json(['ok' => $success]);
    }
}
