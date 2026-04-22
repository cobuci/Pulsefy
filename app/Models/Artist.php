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
 * @property string $artist_id
 * @property ?string $artist_name
 * @property array<int, string> $genres
 * @property ?array<int, array{url: string, height?: int|null, width?: int|null}> $images
 * @property ?int $popularity
 * @property ?string $uri
 * @property ?array<string, mixed> $external_urls
 * @property Carbon $fetched_at
 * @property Carbon $expires_at
 * @property ?Carbon $lastfm_genres_checked_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Collection<int, Album> $albums
 * @property-read ?int $albums_count
 * @property-read Collection<int, UserTopArtist> $topForUsers
 * @property-read ?int $top_for_users_count
 * @property-read Collection<int, Track> $tracks
 * @property-read ?int $tracks_count
 *
 * @mixin \Eloquent
 */
class Artist extends Model
{
    use HasFactory;

    protected $table = 'artists';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'genres' => 'array',
            'images' => 'array',
            'external_urls' => 'array',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
            'lastfm_genres_checked_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function albums(): BelongsToMany
    {
        return $this->belongsToMany(Album::class, 'artist_album', 'artist_model_id', 'album_id')
            ->withTimestamps();
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'artist_track', 'artist_model_id', 'track_id')
            ->withTimestamps();
    }

    public function topForUsers(): HasMany
    {
        return $this->hasMany(UserTopArtist::class, 'artist_model_id');
    }
}
