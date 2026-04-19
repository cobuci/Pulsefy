<?php

namespace App\Models;

use Database\Factories\LibraryFolderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property ?int $parent_id
 * @property string $name
 * @property int $position
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
final class LibraryFolder extends Model
{
    /** @use HasFactory<LibraryFolderFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('position')
            ->orderBy('name');
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class, 'folder_id')
            ->orderByDesc('updated_at');
    }
}
