<?php

namespace App\Jobs;

use App\Events\Reverb\TestToastBroadcasted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DispatchReverbTestToastJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        TestToastBroadcasted::dispatch(
            $this->userId,
            'Reverb test event received successfully.',
        );
    }
}
