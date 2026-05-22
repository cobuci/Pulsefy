<?php

namespace App\Models;

use App\Enums\DailyRecommendationStatus;
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
 * @property DailyRecommendationStatus $status
 * @property ?Carbon $started_at
 * @property ?string $error_message
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 * @property-read Collection<int, RecommendedTrack> $tracks
 * @property-read ?int $tracks_count
 */
class DailyRecommendation extends Model
{
    public const int STALE_MINUTES = 3;

    /** @use HasFactory<DailyRecommendationFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'generated_at' => 'datetime',
            'status' => DailyRecommendationStatus::class,
            'started_at' => 'datetime',
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

    public function isStale(): bool
    {
        if (! $this->status->isPending()) {
            return false;
        }

        if ($this->started_at === null) {
            return false;
        }

        return $this->started_at->copy()->addMinutes(self::STALE_MINUTES)->isPast();
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => DailyRecommendationStatus::Failed,
            'error_message' => $message,
        ]);
    }
}
