<?php

namespace App\Http\Controllers\Player;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Player\Concerns\RespondsWithPlayerJson;
use App\Services\Spotify\Contracts\SpotifyPlaybackProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ControlController extends Controller
{
    use RespondsWithPlayerJson;

    public function __construct(private readonly SpotifyPlaybackProvider $playback) {}

    public function play(Request $request): JsonResponse
    {
        $uri = $request->string('uri')->toString();
        $uris = collect($request->input('uris', []))
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values()
            ->all();
        $offset = $request->input('offset_position');
        $offsetPosition = is_numeric($offset) ? max((int) $offset, 0) : null;

        if ($uris !== []) {
            $success = $this->playback->playMany($request->user(), $uris, $offsetPosition);

            return $this->respondOperation($success);
        }

        $success = $uri === ''
            ? $this->playback->resumePlay($request->user())
            : $this->playback->play($request->user(), $uri);

        return $this->respondOperation($success);
    }

    public function pause(Request $request): JsonResponse
    {
        return $this->respondOperation($this->playback->pause($request->user()));
    }

    public function next(Request $request): JsonResponse
    {
        return $this->respondOperation($this->playback->next($request->user()));
    }

    public function previous(Request $request): JsonResponse
    {
        return $this->respondOperation($this->playback->previous($request->user()));
    }

    public function seek(Request $request): JsonResponse
    {
        return $this->respondOperation($this->playback->seek($request->user(), $request->integer('position_ms')));
    }

    public function volume(Request $request): JsonResponse
    {
        $volumePercent = clamp($request->integer('volume_percent', 50), 0, 100);

        return $this->respondOperation($this->playback->setVolume($request->user(), $volumePercent));
    }

    public function shuffle(Request $request): JsonResponse
    {
        return $this->respondOperation($this->playback->setShuffle($request->user(), $request->boolean('state')));
    }

    public function repeat(Request $request): JsonResponse
    {
        return $this->respondOperation($this->playback->setRepeat($request->user(), $request->string('state', 'off')->toString()));
    }
}
