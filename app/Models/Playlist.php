<?php

namespace App\Models;

use Database\Factories\PlaylistFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ?int $folder_id
 * @property int $position
 * @property bool $is_hidden
 * @property bool $is_liked_playlist
 * @property string $spotify_id
 * @property string $name
 * @property ?string $description
 * @property ?array<int, array<string, mixed>> $images
 * @property ?string $owner_spotify_id
 * @property ?string $owner_name
 * @property bool $is_public
 * @property bool $is_collaborative
 * @property int $tracks_total
 * @property ?string $snapshot_id
 * @property ?string $uri
 * @property ?array<string, mixed> $external_urls
 * @property ?Carbon $synced_at
 * @property ?Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read LibraryFolder|null $folder
 * @property-read Collection<int, PlaylistTrack> $items
 * @property-read ?int $items_count
 * @property-read User $user
 *
 * @mixin \Eloquent
 */
final class Playlist extends Model
{
    /** @use HasFactory<PlaylistFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'external_urls' => 'array',
            'is_public' => 'bool',
            'is_collaborative' => 'bool',
            'is_hidden' => 'bool',
            'is_liked_playlist' => 'bool',
            'synced_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(LibraryFolder::class, 'folder_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlaylistTrack::class)
            ->orderBy('position');
    }
}
