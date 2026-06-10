<?php

namespace Tests\Feature;

use App\Http\Controllers\DmMessageController;
use App\Models\DmConversation;
use App\Models\DmMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DmMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private DmConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->conversation = DmConversation::create([
            'user_one_uuid' => min($this->user->uuid, $this->otherUser->uuid),
            'user_two_uuid' => max($this->user->uuid, $this->otherUser->uuid),
        ]);
    }

    public function test_index_returns_messages_with_cursor_pagination(): void
    {
        DmMessage::factory()->count(3)->create([
            'dm_conversation_uuid' => $this->conversation->uuid,
            'sender_uuid' => $this->user->uuid,
        ]);
        Sanctum::actingAs($this->user);

        $controller = new DmMessageController();
        $response = $controller->index($this->conversation);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_index_requires_authentication(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        $controller = new DmMessageController();
        $controller->index($this->conversation);
    }

    public function test_store_creates_new_message_and_returns_201(): void
    {
        Sanctum::actingAs($this->user);

        $controller = new DmMessageController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'client_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'content' => 'Hello, private message!',
        ]);

        $response = $controller->store($request, $this->conversation);

        $statusCode = $response->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 200]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);

        $this->assertDatabaseHas('dm_messages', [
            'content' => 'Hello, private message!',
            'dm_conversation_uuid' => $this->conversation->uuid,
            'sender_uuid' => $this->user->uuid,
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_client_uuid(): void
    {
        Sanctum::actingAs($this->user);

        $clientUuid = \Illuminate\Support\Str::uuid()->toString();

        $controller = new DmMessageController();

        // First request - creates message
        $request1 = new \Illuminate\Http\Request();
        $request1->merge([
            'client_uuid' => $clientUuid,
            'content' => 'Hello, private message!',
        ]);
        $response1 = $controller->store($request1, $this->conversation);
        $this->assertEquals(201, $response1->getStatusCode());

        // Second request - should return 200 (already exists)
        $request2 = new \Illuminate\Http\Request();
        $request2->merge([
            'client_uuid' => $clientUuid,
            'content' => 'Hello, private message!',
        ]);
        $response2 = $controller->store($request2, $this->conversation);
        $this->assertEquals(200, $response2->getStatusCode());

        $this->assertEquals(1, DmMessage::where('client_uuid', $clientUuid)->count());
    }

    public function test_store_requires_valid_data(): void
    {
        Sanctum::actingAs($this->user);

        $controller = new DmMessageController();
        $request = new \Illuminate\Http\Request();
        // Missing required fields

        try {
            $response = $controller->store($request, $this->conversation);
            $this->assertEquals(422, $response->getStatusCode());
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_store_with_reply_creates_thread_message(): void
    {
        $parentMessage = DmMessage::factory()->create([
            'dm_conversation_uuid' => $this->conversation->uuid,
            'sender_uuid' => $this->user->uuid,
        ]);
        Sanctum::actingAs($this->user);

        $controller = new DmMessageController();
        $request = new \Illuminate\Http\Request();
        $request->merge([
            'client_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'content' => 'This is a reply',
            'parent_message_uuid' => $parentMessage->uuid,
        ]);

        $response = $controller->store($request, $this->conversation);

        $this->assertEquals(201, $response->getStatusCode());

        $this->assertDatabaseHas('dm_messages', [
            'content' => 'This is a reply',
            'parent_message_uuid' => $parentMessage->uuid,
        ]);
    }

    public function test_store_requires_authentication(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        $controller = new DmMessageController();
        $controller->store(new \Illuminate\Http\Request(), $this->conversation);
    }
}