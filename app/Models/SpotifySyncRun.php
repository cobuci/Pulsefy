<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $status
 * @property ?Carbon $started_at
 * @property ?Carbon $finished_at
 * @property ?array<string, mixed> $meta
 * @property ?string $error
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
class SpotifySyncRun extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
