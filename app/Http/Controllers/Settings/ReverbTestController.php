<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ReverbTestController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('settings/ReverbTest', [
            'userId' => $request->user()->id,
            'dispatchToastUrl' => route('reverb-test.dispatch-toast'),
        ]);
    }
}
