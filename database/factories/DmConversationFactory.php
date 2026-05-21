<?php

namespace Database\Factories;

use App\Models\DmConversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DmConversationFactory extends Factory
{
    protected $model = DmConversation::class;

    public function definition(): array
    {
        return [
            'user_one_uuid' => User::factory(),
            'user_two_uuid' => User::factory(),
        ];
    }
}
