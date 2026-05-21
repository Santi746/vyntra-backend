<?php

namespace Database\Factories;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FriendshipFactory extends Factory
{
    protected $model = Friendship::class;

    public function definition(): array
    {
        return [
            'sender_uuid' => User::factory(),
            'receiver_uuid' => User::factory(),
            'status' => fake()->randomElement(['pending', 'accepted', 'blocked']),
        ];
    }
}
