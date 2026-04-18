<?php

namespace App\Events\Reverb;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TestToastBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public int $userId, public string $message) {}

    public function broadcastOn(): Channel
    {
        return new Channel('reverb-test.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'Reverb.TestToastBroadcasted';
    }

    /**
     * @return array{message: string}
     */
    public function broadcastWith(): array
    {
        return [
            'message' => $this->message,
        ];
    }
}
