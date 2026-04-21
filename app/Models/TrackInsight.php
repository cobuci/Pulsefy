<?php

namespace App\Models;

use App\Enums\TrackInsightStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $track_id
 * @property string $track_name
 * @property string $artist_name
 * @property ?string $album_name
 * @property TrackInsightStatus $status
 * @property ?string $summary
 * @property ?string $summary_pt
 * @property ?string $mood
 * @property ?string $mood_pt
 * @property ?string $meaning
 * @property ?string $meaning_pt
 * @property ?string[] $themes
 * @property ?string[] $themes_pt
 * @property ?string[] $trivia
 * @property ?string[] $trivia_pt
 * @property ?string[] $similar
 * @property ?string[] $similar_pt
 * @property ?string $provider
 * @property ?string $model
 * @property ?string $error_message
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class TrackInsight extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => TrackInsightStatus::class,
            'themes' => 'array',
            'themes_pt' => 'array',
            'trivia' => 'array',
            'trivia_pt' => 'array',
            'similar' => 'array',
            'similar_pt' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
