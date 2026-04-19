<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class JobsController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('settings/Jobs');
    }
}
