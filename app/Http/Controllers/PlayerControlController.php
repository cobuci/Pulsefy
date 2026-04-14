<?php

namespace App\Http\Controllers;

use App\Services\Spotify\SpotifyClient;
use App\Services\Spotify\SpotifyDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlayerControlController extends Controller
{
    public function __construct(private readonly SpotifyDataService $spotify) {}

    public function play(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->command(
            $request->user(),
            fn (SpotifyClient $client) => $client->play(),
        ));
    }

    public function pause(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->command(
            $request->user(),
            fn (SpotifyClient $client) => $client->pause(),
        ));
    }

    public function next(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->command(
            $request->user(),
            fn (SpotifyClient $client) => $client->next(),
        ));
    }

    public function previous(Request $request): JsonResponse
    {
        return $this->respond($this->spotify->command(
            $request->user(),
            fn (SpotifyClient $client) => $client->previous(),
        ));
    }

    public function shuffle(Request $request): JsonResponse
    {
        $state = filter_var($request->input('state', true), FILTER_VALIDATE_BOOLEAN);

        return $this->respond($this->spotify->command(
            $request->user(),
            fn (SpotifyClient $client) => $client->shuffle($state),
        ));
    }

    private function respond(bool $success): JsonResponse
    {
        return response()->json(
            ['ok' => $success],
            $success ? 200 : 403,
        );
    }
}
