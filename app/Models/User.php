<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property ?string $email
 * @property ?string $avatar
 * @property string $spotify_token
 * @property string $spotify_refresh_token
 * @property Carbon $spotify_token_expires_at
 * @property ?string $remember_token
 * @property ?Carbon $two_factor_confirmed_at
 * @property ?string $two_factor_secret
 * @property ?string $two_factor_recovery_codes
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read Collection<int, Artist> $artists
 * @property-read ?int $artists_count
 * @property-read Collection<int, LibraryFolder> $libraryFolders
 * @property-read ?int $library_folders_count
 * @property-read Collection<int, LyricPronunciation> $lyricPronunciations
 * @property-read ?int $lyric_pronunciations_count
 * @property-read Collection<int, LyricTranslation> $lyricTranslations
 * @property-read ?int $lyric_translations_count
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read ?int $notifications_count
 * @property-read Collection<int, Playlist> $playlists
 * @property-read ?int $playlists_count
 * @property-read Collection<int, UserRecentPlay> $recentPlays
 * @property-read ?int $recent_plays_count
 * @property-read Collection<int, SpotifyStat> $spotifyStats
 * @property-read ?int $spotify_stats_count
 * @property-read Collection<int, SpotifySyncRun> $syncRuns
 * @property-read ?int $sync_runs_count
 * @property-read Collection<int, UserTopArtist> $topArtists
 * @property-read ?int $top_artists_count
 * @property-read Collection<int, UserTopTrack> $topTracks
 * @property-read ?int $top_tracks_count
 * @property-read Collection<int, Track> $tracks
 * @property-read ?int $tracks_count
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = ['spotify_token', 'spotify_refresh_token', 'remember_token'];

    protected function casts(): array
    {
        return [
            'spotify_token_expires_at' => 'datetime',
        ];
    }

    public function spotifyStats(): HasMany
    {
        return $this->hasMany(SpotifyStat::class);
    }

    public function topArtists(): HasMany
    {
        return $this->hasMany(UserTopArtist::class);
    }

    public function topTracks(): HasMany
    {
        return $this->hasMany(UserTopTrack::class);
    }

    public function recentPlays(): HasMany
    {
        return $this->hasMany(UserRecentPlay::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SpotifySyncRun::class);
    }

    public function libraryFolders(): HasMany
    {
        return $this->hasMany(LibraryFolder::class)
            ->orderBy('position')
            ->orderBy('name');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class)
            ->orderByDesc('updated_at');
    }

    public function lyricTranslations(): HasMany
    {
        return $this->hasMany(LyricTranslation::class);
    }

    public function lyricPronunciations(): HasMany
    {
        return $this->hasMany(LyricPronunciation::class);
    }

    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class, 'user_top_artists', 'user_id', 'artist_model_id')
            ->withPivot(['time_range', 'rank', 'score', 'synced_at'])
            ->withTimestamps();
    }

    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'user_top_tracks', 'user_id', 'track_id')
            ->withPivot(['time_range', 'rank', 'score', 'synced_at'])
            ->withTimestamps();
    }

    public function isSpotifyTokenExpired(): bool
    {
        return $this->spotify_token_expires_at->subMinutes(5)->isPast();
    }
}
