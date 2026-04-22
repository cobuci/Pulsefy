<?php

namespace App\Models;

use Database\Factories\TrackInteractionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $spotify_id
 * @property string $type
 * @property Carbon $interacted_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 */
class TrackInteraction extends Model
{
    /** @use HasFactory<TrackInteractionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'interacted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    #[Scope]
    public function activelySuppressed(Builder $query): void
    {
        $query->where('type', 'skip')->where('expires_at', '>', now());
    }

    #[Scope]
    public function suppressedForUser(Builder $query, int $userId): void
    {
        $query->activelySuppressed()->where('user_id', $userId);
    }
}
