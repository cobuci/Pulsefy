<?php

namespace App\Events\Lyrics;

use App\Models\LyricTranslation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TranslationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $trackId,
        public string $status,
        public ?array $translatedLines = null,
        public ?string $errorMessage = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('App.Models.User.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'Lyrics.TranslationUpdated';
    }

    /**
     * @return array{trackId: string, status: string, translatedLines: ?array, errorMessage: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'trackId' => $this->trackId,
            'status' => $this->status,
            'translatedLines' => $this->translatedLines,
            'errorMessage' => $this->errorMessage,
        ];
    }

    public static function fromTranslation(LyricTranslation $translation): self
    {
        return new self(
            $translation->user_id,
            $translation->track_id,
            $translation->status,
            $translation->translated_lines,
            $translation->error_message,
        );
    }
}
