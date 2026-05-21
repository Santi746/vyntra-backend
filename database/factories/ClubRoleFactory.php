<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\ClubRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubRoleFactory extends Factory
{
    protected $model = ClubRole::class;

    public function definition(): array
    {
        return [
            'club_uuid' => Club::factory(),
            'name' => fake()->randomElement(['Administrador', 'Moderador', 'VIP', 'Socio', 'Colaborador']),
            'color' => fake()->hexColor(),
            'is_fixed' => fake()->boolean(10),
            'permissions' => fake()->numberBetween(1, 255),
        ];
    }
}
