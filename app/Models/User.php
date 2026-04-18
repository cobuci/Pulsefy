<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
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

    public function isSpotifyTokenExpired(): bool
    {
        return $this->spotify_token_expires_at->subMinutes(5)->isPast();
    }
}
