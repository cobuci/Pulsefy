<?php

namespace App\Events\Lyrics;

use App\Models\LyricPronunciation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PronunciationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $trackId,
        public string $status,
        public ?array $romanizedLines = null,
        public ?string $errorMessage = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'Lyrics.PronunciationUpdated';
    }

    /**
     * @return array{trackId: string, status: string, romanizedLines: ?array, errorMessage: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'trackId' => $this->trackId,
            'status' => $this->status,
            'romanizedLines' => $this->romanizedLines,
            'errorMessage' => $this->errorMessage,
        ];
    }

    public static function fromPronunciation(LyricPronunciation $pronunciation): self
    {
        return new self(
            $pronunciation->user_id,
            $pronunciation->track_id,
            $pronunciation->status,
            $pronunciation->romanized_lines,
            $pronunciation->error_message,
        );
    }
}
