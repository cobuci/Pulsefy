<?php

namespace App\Models;

use Database\Factories\LyricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $track_id
 * @property string $artist_name
 * @property string $track_name
 * @property ?string $synced_lyrics
 * @property ?string $plain_lyrics
 * @property bool $is_synced
 * @property string $source
 * @property ?Carbon $fetched_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class Lyric extends Model
{
    /** @use HasFactory<LyricFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_synced' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(LyricTranslation::class);
    }

    public function pronunciations(): HasMany
    {
        return $this->hasMany(LyricPronunciation::class);
    }
}
