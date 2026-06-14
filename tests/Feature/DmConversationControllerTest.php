<?php

namespace Tests\Feature;

use App\Models\DmConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DmConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_conversations_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $otherUsers = User::factory()->count(3)->create();

        foreach ($otherUsers as $other) {
            DmConversation::create([
                'user_one_uuid' => min($user->uuid, $other->uuid),
                'user_two_uuid' => max($user->uuid, $other->uuid),
            ]);
        }

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/user/dm-conversations');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['next_cursor', 'per_page']]);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/user/dm-conversations');

        $response->assertStatus(401);
    }

    public function test_show_returns_conversation_details(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $otherUser->uuid,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson("/api/dm-conversations/{$conversation->uuid}");

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_show_returns_403_for_non_participant(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user1->uuid,
            'user_two_uuid' => $user2->uuid,
        ]);

        $this->actingAs($user3, 'sanctum');

        $response = $this->getJson("/api/dm-conversations/{$conversation->uuid}");

        $response->assertStatus(403);
    }

    public function test_show_requires_authentication(): void
    {
        $conversation = DmConversation::factory()->create();

        $response = $this->getJson("/api/dm-conversations/{$conversation->uuid}");

        $response->assertStatus(401);
    }

    public function test_store_creates_new_conversation_and_returns_201(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/dm-conversations', [
            'recipient_uuid' => $recipient->uuid,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('dm_conversations', [
            'user_one_uuid' => min($user->uuid, $recipient->uuid),
            'user_two_uuid' => max($user->uuid, $recipient->uuid),
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_conversation(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        DmConversation::create([
            'user_one_uuid' => min($user->uuid, $recipient->uuid),
            'user_two_uuid' => max($user->uuid, $recipient->uuid),
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/dm-conversations', [
            'recipient_uuid' => $recipient->uuid,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_store_prevents_self_conversation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/dm-conversations', [
            'recipient_uuid' => $user->uuid,
        ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_valid_recipient(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/dm-conversations', [
            'recipient_uuid' => 'nonexistent-uuid',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_requires_authentication(): void
    {
        $recipient = User::factory()->create();

        $response = $this->postJson('/api/dm-conversations', [
            'recipient_uuid' => $recipient->uuid,
        ]);

        $response->assertStatus(401);
    }
}
