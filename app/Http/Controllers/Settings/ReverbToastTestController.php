<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchReverbTestToastJob;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ReverbToastTestController extends Controller
{
    public function __invoke(Request $request): Response
    {
        DispatchReverbTestToastJob::dispatch($request->user()->id)
            ->onQueue('spotify-sync');

        return response()->noContent();
    }
}
