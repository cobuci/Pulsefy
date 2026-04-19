<?php

namespace Database\Factories;

use App\Models\LibraryFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryFolder>
 */
class LibraryFolderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'parent_id' => null,
            'name' => fake()->words(2, true),
            'position' => fake()->numberBetween(0, 20),
        ];
    }
}
