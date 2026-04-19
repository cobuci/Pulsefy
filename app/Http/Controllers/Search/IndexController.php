<?php

namespace App\Http\Controllers\Search;

use App\Http\Controllers\Controller;
use App\Services\Search\SpotifySearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IndexController extends Controller
{
    public function __construct(
        private readonly SpotifySearchService $searchService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim()->toString();

        $results = $this->searchService->search($request->user(), $query);

        return response()->json([
            'query' => $query,
            'quick_actions' => [
                [
                    'id' => 'go-dashboard',
                    'type' => 'action',
                    'title' => 'Go to Dashboard',
                    'subtitle' => 'Home',
                    'href' => route('dashboard'),
                ],
                [
                    'id' => 'browse-artists',
                    'type' => 'action',
                    'title' => 'Browse Artists',
                    'subtitle' => 'Library',
                    'href' => route('artists.index'),
                ],
                [
                    'id' => 'browse-recently-played',
                    'type' => 'action',
                    'title' => 'Recently Played',
                    'subtitle' => 'History',
                    'href' => route('recently-played'),
                ],
            ],
            ...$results,
        ]);
    }
}
