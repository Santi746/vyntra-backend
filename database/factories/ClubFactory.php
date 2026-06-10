<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'description' => fake()->sentence(),
            'avatar_url' => fake()->imageUrl(200, 200, 'abstract'),
            'banner_url' => fake()->imageUrl(800, 400, 'nature'),
            'category_tag' => fake()->randomElement(['gaming', 'programming', 'music', 'anime']),
            'owner_uuid' => User::factory(),
            'client_uuid' => fake()->uuid(),
        ];
    }
}
