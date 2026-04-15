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

    public function seek(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->seek($request->user(), $request->integer('position_ms')));
    }

    public function volume(Request $request): JsonResponse
    {
        $volumePercent = clamp($request->integer('volume_percent', 50), 0, 100);

        return $this->respond($this->spotify->setVolume($request->user(), $volumePercent));
    }

    private function respond(bool $success): JsonResponse
    {
        return response()->json(['ok' => $success]);
    }
}
