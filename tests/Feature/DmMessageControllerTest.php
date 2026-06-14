<?php

namespace Tests\Feature;

use App\Models\DmConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DmMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_messages_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $otherUser->uuid,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson("/api/dm-conversations/{$conversation->uuid}/messages");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['next_cursor', 'per_page']]);
    }

    public function test_index_returns_403_for_non_participant(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user1->uuid,
            'user_two_uuid' => $user2->uuid,
        ]);

        $this->actingAs($user3, 'sanctum');

        $response = $this->getJson("/api/dm-conversations/{$conversation->uuid}/messages");

        $response->assertStatus(403);
    }

    public function test_index_requires_authentication(): void
    {
        $conversation = DmConversation::factory()->create();

        $response = $this->getJson("/api/dm-conversations/{$conversation->uuid}/messages");

        $response->assertStatus(401);
    }

    public function test_store_creates_new_message_and_returns_201(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $otherUser->uuid,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/dm-conversations/{$conversation->uuid}/messages", [
            'content' => 'Hello, world!',
            'client_uuid' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('dm_messages', [
            'dm_conversation_uuid' => $conversation->uuid,
            'sender_uuid' => $user->uuid,
            'content' => 'Hello, world!',
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_client_uuid(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $otherUser->uuid,
        ]);
        $clientUuid = Str::uuid()->toString();

        $this->actingAs($user, 'sanctum');

        $this->postJson("/api/dm-conversations/{$conversation->uuid}/messages", [
            'content' => 'First',
            'client_uuid' => $clientUuid,
        ])->assertStatus(201);

        $response = $this->postJson("/api/dm-conversations/{$conversation->uuid}/messages", [
            'content' => 'Second',
            'client_uuid' => $clientUuid,
        ]);

        $response->assertStatus(200);
    }

    public function test_store_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $otherUser->uuid,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/dm-conversations/{$conversation->uuid}/messages", [
            'client_uuid' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
    }

    public function test_store_returns_403_for_non_participant(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user1->uuid,
            'user_two_uuid' => $user2->uuid,
        ]);

        $this->actingAs($user3, 'sanctum');

        $response = $this->postJson("/api/dm-conversations/{$conversation->uuid}/messages", [
            'content' => 'Should not work',
            'client_uuid' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_store_requires_authentication(): void
    {
        $conversation = DmConversation::factory()->create();

        $response = $this->postJson("/api/dm-conversations/{$conversation->uuid}/messages", [
            'content' => 'Hello',
            'client_uuid' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(401);
    }
}
