<?php

namespace App\Events\Library;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class LibraryPlaylistTracksSynced implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public string $playlistId,
        public bool $hasFailure,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'Library.PlaylistTracksSynced';
    }

    /**
     * @return array{playlistId: string, hasFailure: bool}
     */
    public function broadcastWith(): array
    {
        return [
            'playlistId' => $this->playlistId,
            'hasFailure' => $this->hasFailure,
        ];
    }
}
