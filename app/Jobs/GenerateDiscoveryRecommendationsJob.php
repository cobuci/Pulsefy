<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class GenerateDiscoveryRecommendationsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public readonly User $user,
    ) {}

    public function uniqueId(): string
    {
        return $this->user->id.'_'.now()->toDateString();
    }

    public function handle(DiscoveryService $discovery): void
    {
        $discovery->generate($this->user);
    }
}
