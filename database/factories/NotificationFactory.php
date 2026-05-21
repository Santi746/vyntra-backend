<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'type' => fake()->randomElement(['friend_request', 'club_invite', 'mention']),
            'data' => [
                'message' => fake()->sentence(),
                'sender_username' => fake()->userName(),
            ],
            'is_read' => fake()->boolean(20), // 20% de probabilidad de estar leída
            'client_uuid' => fake()->uuid(),
        ];
    }
}
