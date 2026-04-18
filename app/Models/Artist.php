<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $artist_id
 * @property ?string $artist_name
 * @property array<int, string> $genres
 * @property Carbon $fetched_at
 * @property Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
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
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
