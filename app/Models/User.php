<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $hidden = ['spotify_token', 'spotify_refresh_token', 'remember_token'];

    protected function casts(): array
    {
        return [
            'spotify_token_expires_at' => 'datetime',
        ];
    }
}
