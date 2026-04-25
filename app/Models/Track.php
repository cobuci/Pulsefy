<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $spotify_id
 * @property ?int $album_id
 * @property string $name
 * @property int $duration_ms
 * @property bool $explicit
 * @property ?string $image_url
 * @property ?Carbon $metadata_synced_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Album|null $album
 * @property-read Collection<int, Artist> $artists
 * @property-read ?int $artists_count
 * @property-read Collection<int, UserRecentPlay> $recentPlays
 * @property-read ?int $recent_plays_count
 * @property-read Collection<int, UserTopTrack> $topForUsers
 * @property-read ?int $top_for_users_count
 * @property-read Collection<int, RecommendedTrack> $recommendations
 * @property-read Collection<int, DiscoveryLikedTrack> $discoveryLikes
 * @property-read Collection<int, TrackInteraction> $interactions
 */
class Track extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'explicit' => 'bool',
            'metadata_synced_at' => 'datetime',
        ];
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'artist_track', 'track_id', 'artist_model_id')
            ->withTimestamps();
    }

    public function topForUsers(): HasMany
    {
        return $this->hasMany(UserTopTrack::class);
    }

    public function recentPlays(): HasMany
    {
        return $this->hasMany(UserRecentPlay::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(RecommendedTrack::class);
    }

    public function discoveryLikes(): HasMany
    {
        return $this->hasMany(DiscoveryLikedTrack::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(TrackInteraction::class);
    }
}
