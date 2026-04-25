<?php

namespace App\Models;

use Database\Factories\DiscoveryLikedTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $track_id
 * @property Carbon $liked_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 * @property-read Track $track
 */
class DiscoveryLikedTrack extends Model
{
    /** @use HasFactory<DiscoveryLikedTrackFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'liked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
