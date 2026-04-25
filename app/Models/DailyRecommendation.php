<?php

namespace App\Models;

use Database\Factories\DailyRecommendationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property Carbon $date
 * @property Carbon $generated_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, RecommendedTrack> $tracks
 * @property-read ?int $tracks_count
 */
class DailyRecommendation extends Model
{
    /** @use HasFactory<DailyRecommendationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'generated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(RecommendedTrack::class)->orderBy('position');
    }
}
