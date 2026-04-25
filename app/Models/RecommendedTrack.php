<?php

namespace App\Models;

use Database\Factories\RecommendedTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $daily_recommendation_id
 * @property int $track_id
 * @property int $match_score
 * @property int $position
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read DailyRecommendation $recommendation
 * @property-read Track $track
 */
class RecommendedTrack extends Model
{
    /** @use HasFactory<RecommendedTrackFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'match_score' => 'integer',
            'position' => 'integer',
        ];
    }

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(DailyRecommendation::class, 'daily_recommendation_id');
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
