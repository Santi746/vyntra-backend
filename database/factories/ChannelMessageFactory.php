<?php

namespace Database\Factories;

use App\Models\ChannelMessage;
use App\Models\ClubChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelMessageFactory extends Factory
{
    protected $model = ChannelMessage::class;

    public function definition(): array
    {
        return [
            // Contenido del mensaje corto
            'content' => fake()->sentence(fake()->numberBetween(3, 12)),
            'status' => 'sent',
            'client_uuid' => fake()->uuid(),

            // Relaciones requeridas
            'club_channel_uuid' => ClubChannel::factory(),
            'sender_uuid' => User::factory(),

            // Hilos: nulo por defecto para evitar bucles infinitos de creación recursiva
            'parent_message_uuid' => null,
        ];
    }
}
