<?php

namespace App\Http\Controllers;

use App\Services\Spotify\Contracts\SpotifyStatsProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

final class RecentlyPlayedController extends Controller
{
    public function __invoke(Request $request, SpotifyStatsProvider $spotify): Response
    {
        $user = $request->user();

        return Inertia::render('RecentlyPlayed', [
            'playGroups' => Inertia::defer(fn () => $this->groupPlaysByDay($spotify->recentlyPlayed($user))),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $plays
     * @return array<int, array{label: string, entries: array<int, array{track: array<string, mixed>, lastPlayedAt: string, count: int}>}>
     */
    private function groupPlaysByDay(array $plays): array
    {
        $grouped = [];

        foreach ($plays as $play) {
            $playedAt = (string) ($play['played_at'] ?? '');
            $trackId = (string) data_get($play, 'track.id', '');

            if ($playedAt === '' || $trackId === '') {
                continue;
            }

            $label = $this->formatGroupDate($playedAt);

            if (! isset($grouped[$label])) {
                $grouped[$label] = [];
            }

            if (! isset($grouped[$label][$trackId])) {
                $grouped[$label][$trackId] = [
                    'track' => $play['track'],
                    'lastPlayedAt' => $playedAt,
                    'count' => 1,
                ];

                continue;
            }

            $grouped[$label][$trackId]['count']++;

            if ($playedAt > $grouped[$label][$trackId]['lastPlayedAt']) {
                $grouped[$label][$trackId]['lastPlayedAt'] = $playedAt;
            }
        }

        $result = [];

        foreach ($grouped as $label => $entries) {
            $result[] = [
                'label' => $label,
                'entries' => array_values($entries),
            ];
        }

        return $result;
    }

    private function formatGroupDate(string $isoString): string
    {
        $date = Carbon::parse($isoString);
        $today = now()->startOfDay();
        $yesterday = $today->copy()->subDay();
        $playDate = $date->copy()->startOfDay();

        if ($playDate->equalTo($today)) {
            return 'Today';
        }

        if ($playDate->equalTo($yesterday)) {
            return 'Yesterday';
        }

        return $date->translatedFormat('l, F j');
    }
}
