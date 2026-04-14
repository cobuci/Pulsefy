<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'spotify_id' => fake()->unique()->numerify('spotify_##########'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'avatar' => fake()->imageUrl(200, 200, 'people'),
            'spotify_token' => fake()->sha256(),
            'spotify_refresh_token' => fake()->sha256(),
            'spotify_token_expires_at' => Carbon::now()->addHour(),
            'remember_token' => null,
        ];
    }
}
