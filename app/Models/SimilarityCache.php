<?php

namespace App\Models;

use Database\Factories\SimilarityCacheFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $key
 * @property array<string, mixed> $payload
 * @property Carbon $fetched_at
 * @property Carbon $expires_at
 * @property ?Carbon $created_at
 * @property ?Carbon $updated_at
 */
class SimilarityCache extends Model
{
    /** @use HasFactory<SimilarityCacheFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'fetched_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    #[Scope]
    public function valid(Builder $query): void
    {
        $query->where('expires_at', '>', now());
    }

    #[Scope]
    public function stale(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
