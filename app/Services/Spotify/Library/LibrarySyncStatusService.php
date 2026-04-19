<?php

namespace App\Services\Spotify\Library;

use Illuminate\Support\Facades\Cache;

final class LibrarySyncStatusService
{
    private const int TTL_SECONDS = 3600;

    /**
     * @return array{isRunning: bool, hasFailure: bool, completed: int, total: int, progress: int, updatedAt: ?string}
     */
    public function userStatus(int $userId): array
    {
        $status = Cache::get($this->userKey($userId));

        if (! is_array($status)) {
            return $this->defaultUserStatus();
        }

        return [
            'isRunning' => (bool) ($status['isRunning'] ?? false),
            'hasFailure' => (bool) ($status['hasFailure'] ?? false),
            'completed' => (int) ($status['completed'] ?? 0),
            'total' => max(0, (int) ($status['total'] ?? 0)),
            'progress' => (int) ($status['progress'] ?? 0),
            'updatedAt' => is_string($status['updatedAt'] ?? null) ? $status['updatedAt'] : null,
        ];
    }

    public function startUserSync(int $userId, int $total): void
    {
        $this->storeUserStatus($userId, [
            'isRunning' => true,
            'hasFailure' => false,
            'completed' => 0,
            'total' => max(0, $total),
            'progress' => 0,
            'updatedAt' => now()->toIso8601String(),
        ]);
    }

    public function updateUserProgress(int $userId, int $completed, int $total, bool $hasFailure = false): void
    {
        $safeTotal = max(0, $total);
        $safeCompleted = max(0, min($safeTotal, $completed));
        $progress = $safeTotal === 0 ? 0 : (int) round(($safeCompleted / $safeTotal) * 100);

        $this->storeUserStatus($userId, [
            'isRunning' => true,
            'hasFailure' => $hasFailure,
            'completed' => $safeCompleted,
            'total' => $safeTotal,
            'progress' => $progress,
            'updatedAt' => now()->toIso8601String(),
        ]);
    }

    public function finishUserSync(int $userId, bool $hasFailure): void
    {
        $current = $this->userStatus($userId);
        $total = $current['total'];
        $completed = $current['completed'];
        $progress = $total === 0 ? 0 : (int) round(($completed / $total) * 100);

        $this->storeUserStatus($userId, [
            'isRunning' => false,
            'hasFailure' => $hasFailure,
            'completed' => $completed,
            'total' => $total,
            'progress' => $progress,
            'updatedAt' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array{isRunning: bool, hasFailure: bool, updatedAt: ?string}
     */
    public function playlistStatus(int $userId, string $playlistSpotifyId): array
    {
        $status = Cache::get($this->playlistKey($userId, $playlistSpotifyId));

        if (! is_array($status)) {
            return [
                'isRunning' => false,
                'hasFailure' => false,
                'updatedAt' => null,
            ];
        }

        return [
            'isRunning' => (bool) ($status['isRunning'] ?? false),
            'hasFailure' => (bool) ($status['hasFailure'] ?? false),
            'updatedAt' => is_string($status['updatedAt'] ?? null) ? $status['updatedAt'] : null,
        ];
    }

    public function startPlaylistSync(int $userId, string $playlistSpotifyId): void
    {
        Cache::put($this->playlistKey($userId, $playlistSpotifyId), [
            'isRunning' => true,
            'hasFailure' => false,
            'updatedAt' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    public function finishPlaylistSync(int $userId, string $playlistSpotifyId, bool $hasFailure): void
    {
        Cache::put($this->playlistKey($userId, $playlistSpotifyId), [
            'isRunning' => false,
            'hasFailure' => $hasFailure,
            'updatedAt' => now()->toIso8601String(),
        ], self::TTL_SECONDS);
    }

    private function storeUserStatus(int $userId, array $status): void
    {
        Cache::put($this->userKey($userId), $status, self::TTL_SECONDS);
    }

    private function userKey(int $userId): string
    {
        return 'library-sync:user:'.$userId;
    }

    private function playlistKey(int $userId, string $playlistSpotifyId): string
    {
        return 'library-sync:user:'.$userId.':playlist:'.$playlistSpotifyId;
    }

    /**
     * @return array{isRunning: bool, hasFailure: bool, completed: int, total: int, progress: int, updatedAt: ?string}
     */
    private function defaultUserStatus(): array
    {
        return [
            'isRunning' => false,
            'hasFailure' => false,
            'completed' => 0,
            'total' => 0,
            'progress' => 0,
            'updatedAt' => null,
        ];
    }
}
