<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $artist_model_id
 * @property string $time_range
 * @property int $rank
 * @property int $score
 * @property Carbon $synced_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class UserTopArtist extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rank' => 'int',
            'score' => 'int',
            'synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class, 'artist_model_id');
    }
}
