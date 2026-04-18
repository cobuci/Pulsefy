<?php

namespace App\Events\Spotify;

use App\Models\User;
use App\Services\Spotify\Sync\SpotifySyncStatusService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SyncStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @var array{isRunning: bool, hasFailure: bool, completed: int, total: int, progress: int, updatedAt: ?string}
     */
    public array $status;

    public function __construct(public int $userId)
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            $this->status = [
                'isRunning' => false,
                'hasFailure' => false,
                'completed' => 0,
                'total' => 3,
                'progress' => 0,
                'updatedAt' => null,
            ];

            return;
        }

        $this->status = app(SpotifySyncStatusService::class)->forUser($user);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'Spotify.SyncStatusUpdated';
    }

    /**
     * @return array{status: array{isRunning: bool, hasFailure: bool, completed: int, total: int, progress: int, updatedAt: ?string}}
     */
    public function broadcastWith(): array
    {
        return [
            'status' => $this->status,
        ];
    }
}
