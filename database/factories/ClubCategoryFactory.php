<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\ClubCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubCategoryFactory extends Factory
{
    protected $model = ClubCategory::class;

    public function definition(): array
    {
        return [
            // Nombres de categorías realistas en chats
            'name' => fake()->randomElement(['Información', 'General', 'Comunidad', 'Gaming', 'Música']),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_private' => fake()->boolean(10), // 10% de probabilidad de ser privada
            
            'client_uuid' => fake()->uuid(),

            // Relación con Club
            'club_uuid' => Club::factory(),
        ];
    }
}
