<?php

namespace App\Services\Spotify\Sync;

use App\Models\SpotifySyncRun;
use App\Models\User;

final class SpotifySyncStatusService
{
    /**
     * @return array{isRunning: bool, hasFailure: bool, completed: int, total: int, progress: int, updatedAt: ?string}
     */
    public function forUser(User $user): array
    {
        $latestRunIds = SpotifySyncRun::query()
            ->where('user_id', $user->id)
            ->whereIn('type', ['top_artists', 'top_tracks', 'recent_plays'])
            ->selectRaw('MAX(id) as id')
            ->groupBy('type')
            ->pluck('id')
            ->all();

        if ($latestRunIds === []) {
            return [
                'isRunning' => false,
                'hasFailure' => false,
                'completed' => 0,
                'total' => 3,
                'progress' => 0,
                'updatedAt' => null,
            ];
        }

        $latestRuns = SpotifySyncRun::query()
            ->whereIn('id', $latestRunIds)
            ->get();

        $completed = $latestRuns->where('status', 'completed')->count();
        $isRunning = $latestRuns->contains(fn (SpotifySyncRun $run): bool => $run->status === 'running');
        $hasFailure = $latestRuns->contains(fn (SpotifySyncRun $run): bool => $run->status === 'failed');

        $updatedAt = $latestRuns
            ->pluck('updated_at')
            ->filter()
            ->max();

        $total = 3;

        return [
            'isRunning' => $isRunning,
            'hasFailure' => $hasFailure,
            'completed' => $completed,
            'total' => $total,
            'progress' => (int) round(($completed / $total) * 100),
            'updatedAt' => $updatedAt?->toIso8601String(),
        ];
    }
}
