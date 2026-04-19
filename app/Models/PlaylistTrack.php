<?php

namespace App\Models;

use Database\Factories\PlaylistTrackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $playlist_id
 * @property ?int $track_id
 * @property string $spotify_track_id
 * @property int $position
 * @property ?Carbon $added_at
 * @property ?string $added_by_spotify_id
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
final class PlaylistTrack extends Model
{
    /** @use HasFactory<PlaylistTrackFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'added_at' => 'datetime',
        ];
    }

    public function playlist(): BelongsTo
    {
        return $this->belongsTo(Playlist::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }
}
