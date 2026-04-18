<?php

namespace App\Http\Controllers\Player\Concerns;

use Illuminate\Http\JsonResponse;

trait RespondsWithPlayerJson
{
    protected function respondOperation(bool $success, int $failureStatus = 200): JsonResponse
    {
        return response()->json(
            ['ok' => $success],
            $success ? 200 : $failureStatus,
        );
    }

    protected function respondPayload(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status);
    }
}
