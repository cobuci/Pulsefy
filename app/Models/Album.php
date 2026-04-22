<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property ?string $album_type
 * @property ?string $release_date
 * @property ?array<int, array<string, mixed>> $images
 * @property int $total_tracks
 * @property ?Carbon $metadata_synced_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Collection<int, Artist> $artists
 * @property-read ?int $artists_count
 * @property-read Collection<int, Track> $tracks
 * @property-read ?int $tracks_count
 *
 * @mixin \Eloquent
 */
class Album extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'metadata_synced_at' => 'datetime',
        ];
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'artist_album', 'album_id', 'artist_model_id')
            ->withTimestamps();
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(Track::class);
    }
}
