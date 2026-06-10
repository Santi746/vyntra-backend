<?php

namespace Database\Factories;

use App\Models\ClubCategory;
use App\Models\ClubChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubChannelFactory extends Factory
{
    protected $model = ClubChannel::class;

    public function definition(): array
    {
        return [
            // Nombres típicos de canales de chat en un club
            'name' => fake()->randomElement(['anuncios', 'charla-general', 'memes', 'ayuda-soporte', 'musica-y-relax']),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['text', 'voice']),
            'sort_order' => fake()->numberBetween(0, 10),
            'is_private' => fake()->boolean(5), // 5% de probabilidad de ser privado
            'client_uuid' => fake()->uuid(),
            
            // Relación con Categoría
            'category_uuid' => ClubCategory::factory(),
        ];
    }
}
