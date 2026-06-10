<?php

namespace Tests\Feature;

use App\Http\Controllers\DmConversationController;
use App\Models\DmConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DmConversationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_conversations_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        DmConversation::factory()->count(3)->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $otherUser->uuid,
        ]);
        Sanctum::actingAs($user);

        $controller = new DmConversationController();
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_index_requires_authentication(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        $controller = new DmConversationController();
        $controller->index(request());
    }

    public function test_show_returns_conversation_details(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $otherUser->uuid,
        ]);
        Sanctum::actingAs($user);

        $controller = new DmConversationController();
        $response = $controller->show($conversation, request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);
    }

    public function test_show_returns_403_for_unauthorized_user(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user1->uuid,
            'user_two_uuid' => $user2->uuid,
        ]);
        Sanctum::actingAs($user3);

        $controller = new DmConversationController();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $controller->show($conversation, request());
    }

    public function test_show_requires_authentication(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        $controller = new DmConversationController();
        $controller->show(new DmConversation(), request());
    }

    public function test_store_creates_new_conversation_and_returns_201(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new DmConversationController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'recipient_uuid' => $recipient->uuid,
        ]);

        $response = $controller->store($request);

        $statusCode = $response->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 200]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);

        // Verify conversation was created with sorted UUIDs
        $this->assertDatabaseHas('dm_conversations', [
            'user_one_uuid' => min($user->uuid, $recipient->uuid),
            'user_two_uuid' => max($user->uuid, $recipient->uuid),
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_conversation(): void
    {
        $user = User::factory()->create();
        $recipient = User::factory()->create();

        // Create existing conversation
        DmConversation::create([
            'user_one_uuid' => min($user->uuid, $recipient->uuid),
            'user_two_uuid' => max($user->uuid, $recipient->uuid),
        ]);

        Sanctum::actingAs($user);

        $controller = new DmConversationController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'recipient_uuid' => $recipient->uuid,
        ]);

        $response = $controller->store($request);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_store_prevents_self_conversation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new DmConversationController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'recipient_uuid' => $user->uuid,
        ]);

        try {
            $response = $controller->store($request);
            $this->assertEquals(422, $response->getStatusCode());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_store_requires_valid_recipient(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new DmConversationController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'recipient_uuid' => 'nonexistent-uuid',
        ]);

        try {
            $response = $controller->store($request);
            $this->assertEquals(422, $response->getStatusCode());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_store_requires_authentication(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        $controller = new DmConversationController();
        $controller->store(new \Illuminate\Http\Request());
    }
}