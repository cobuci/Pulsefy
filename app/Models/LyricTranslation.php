<?php

namespace App\Models;

use Database\Factories\LyricTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $lyric_id
 * @property string $track_id
 * @property 'queued'|'processing'|'ready'|'failed' $status
 * @property ?array<int, array{index: int, timestamp: ?string, text: string, source_lang: string, pt_br: ?string, en: ?string}> $translated_lines
 * @property ?string $provider
 * @property ?string $model
 * @property ?Carbon $requested_at
 * @property ?Carbon $started_at
 * @property ?Carbon $completed_at
 * @property ?string $error_message
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 * @property-read User $user
 * @property-read Lyric $lyric
 */
class LyricTranslation extends Model
{
    /** @use HasFactory<LyricTranslationFactory> */
    use HasFactory;

    public const string STATUS_QUEUED = 'queued';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_READY = 'ready';

    public const string STATUS_FAILED = 'failed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'translated_lines' => 'array',
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lyric(): BelongsTo
    {
        return $this->belongsTo(Lyric::class);
    }
}
