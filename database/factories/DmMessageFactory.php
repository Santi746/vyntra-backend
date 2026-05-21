<?php

namespace Database\Factories;

use App\Models\DmConversation;
use App\Models\DmMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DmMessageFactory extends Factory
{
    protected $model = DmMessage::class;

    public function definition(): array
    {
        return [
            'dm_conversation_uuid' => DmConversation::factory(),
            'sender_uuid' => User::factory(),
            'parent_message_uuid' => null,
            'content' => fake()->sentence(fake()->numberBetween(3, 10)),
            'status' => 'sent',
            'client_uuid' => fake()->uuid(),
        ];
    }
}
