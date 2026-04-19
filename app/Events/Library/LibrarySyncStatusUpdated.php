<?php

namespace App\Events\Library;

use App\Services\Spotify\Library\LibrarySyncStatusService;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LibrarySyncStatusUpdated implements ShouldBroadcastNow
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
        $this->status = app(LibrarySyncStatusService::class)->userStatus($this->userId);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'Library.SyncStatusUpdated';
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
