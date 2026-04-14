<?php

namespace App\Models;

use Database\Factories\LyricFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lyric extends Model
{
    /** @use HasFactory<LyricFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_synced' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }
}
