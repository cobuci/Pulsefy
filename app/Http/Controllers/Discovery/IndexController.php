<?php

namespace App\Http\Controllers\Discovery;

use App\Http\Controllers\Controller;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class IndexController extends Controller
{
    public function __invoke(Request $request, DiscoveryService $discovery): Response
    {
        return Inertia::render('Discovery/Index', [
            'recommendations' => Inertia::defer(fn () => $discovery->generate($request->user())),
        ]);
    }
}
