<?php

namespace App\Events\TrackInsights;

use App\Enums\TrackInsightStatus;
use App\Models\TrackInsight;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TrackInsightsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $trackId,
        public readonly TrackInsightStatus $status,
        public readonly ?array $insights = null,
        public readonly ?string $errorMessage = null,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel('track-insights.'.$this->trackId);
    }

    public function broadcastAs(): string
    {
        return 'TrackInsights.Updated';
    }

    /**
     * @return array{trackId: string, status: string, insights: ?array, errorMessage: ?string}
     */
    public function broadcastWith(): array
    {
        return [
            'trackId' => $this->trackId,
            'status' => $this->status->value,
            'insights' => $this->insights,
            'errorMessage' => $this->errorMessage,
        ];
    }

    public static function fromInsight(TrackInsight $insight): self
    {
        $insights = null;

        if ($insight->status === TrackInsightStatus::Ready) {
            $insights = [
                'summary' => $insight->summary,
                'summary_pt' => $insight->summary_pt,
                'mood' => $insight->mood,
                'mood_pt' => $insight->mood_pt,
                'meaning' => $insight->meaning,
                'meaning_pt' => $insight->meaning_pt,
                'themes' => $insight->themes,
                'themes_pt' => $insight->themes_pt,
                'trivia' => $insight->trivia,
                'trivia_pt' => $insight->trivia_pt,
                'similar' => $insight->similar,
                'similar_pt' => $insight->similar_pt,
            ];
        }

        return new self(
            trackId: $insight->track_id,
            status: $insight->status,
            insights: $insights,
            errorMessage: $insight->error_message,
        );
    }
}
