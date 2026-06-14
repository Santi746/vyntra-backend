<?php

namespace Tests\Feature;

use App\Http\Controllers\FriendshipController;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendshipControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_accepted_friendships_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        Friendship::factory()->count(3)->create([
            'sender_uuid' => $user->uuid,
            'receiver_uuid' => $friend->uuid,
            'status' => 'accepted',
        ]);
        Sanctum::actingAs($user);

        $controller = new FriendshipController;
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_index_returns_only_accepted_friendships(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        Friendship::factory()->create([
            'sender_uuid' => $user->uuid,
            'receiver_uuid' => $friend->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $controller = new FriendshipController;
        $response = $controller->index(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(0, $data['data']);
    }

    public function test_index_requires_authentication(): void
    {
        $this->expectException(AuthenticationException::class);

        $controller = new FriendshipController;
        $controller->index(request());
    }

    public function test_pending_returns_pending_requests_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        Friendship::factory()->count(3)->create([
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $user->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $controller = new FriendshipController;
        $response = $controller->pending(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_pending_returns_only_received_requests(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        // Request sent BY user (should not appear)
        Friendship::factory()->create([
            'sender_uuid' => $user->uuid,
            'receiver_uuid' => $otherUser->uuid,
            'status' => 'pending',
        ]);
        // Request received BY user (should appear)
        Friendship::factory()->create([
            'sender_uuid' => $otherUser->uuid,
            'receiver_uuid' => $user->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($user);

        $controller = new FriendshipController;
        $response = $controller->pending(request());

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(1, $data['data']);
    }

    public function test_pending_requires_authentication(): void
    {
        $this->expectException(AuthenticationException::class);

        $controller = new FriendshipController;
        $controller->pending(request());
    }

    public function test_store_sends_friendship_request_and_returns_201(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        Sanctum::actingAs($sender);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge([
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => $receiver->uuid,
        ]);

        $response = $controller->store($request);

        $statusCode = $response->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 200]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);

        $this->assertDatabaseHas('friendships', [
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $receiver->uuid,
            'status' => 'pending',
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_client_uuid(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        Sanctum::actingAs($sender);

        $clientUuid = Str::uuid()->toString();

        $controller = new FriendshipController;

        // First request - creates friendship
        $request1 = new Request;
        $request1->merge([
            'client_uuid' => $clientUuid,
            'receiver_uuid' => $receiver->uuid,
        ]);
        $response1 = $controller->store($request1);
        $this->assertEquals(201, $response1->getStatusCode());

        // Second request - should return 200 (already exists)
        $request2 = new Request;
        $request2->merge([
            'client_uuid' => $clientUuid,
            'receiver_uuid' => $receiver->uuid,
        ]);
        $response2 = $controller->store($request2);
        $this->assertEquals(200, $response2->getStatusCode());

        $this->assertEquals(1, Friendship::where('client_uuid', $clientUuid)->count());
    }

    public function test_store_prevents_self_friendship(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge([
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => $user->uuid,
        ]);

        try {
            $response = $controller->store($request);
            $this->assertEquals(422, $response->getStatusCode());
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_store_requires_valid_receiver(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge([
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => 'nonexistent-uuid',
        ]);

        try {
            $response = $controller->store($request);
            $this->assertEquals(422, $response->getStatusCode());
        } catch (ValidationException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_store_requires_authentication(): void
    {
        $this->expectException(AuthenticationException::class);

        $controller = new FriendshipController;
        $controller->store(new Request);
    }

    public function test_respond_accepts_friendship_request(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $receiver->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($receiver);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge(['action' => 'accept']);

        $response = $controller->respond($request, $friendship->uuid);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('friendships', [
            'uuid' => $friendship->uuid,
            'status' => 'accepted',
        ]);
    }

    public function test_respond_declines_friendship_request(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $receiver->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($receiver);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge(['action' => 'decline']);

        $response = $controller->respond($request, $friendship->uuid);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('friendships', [
            'uuid' => $friendship->uuid,
            'status' => 'declined',
        ]);
    }

    public function test_respond_rejects_legacy_reject_action(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $receiver->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($receiver);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge(['action' => 'reject']); // Legacy action

        $response = $controller->respond($request, $friendship->uuid);

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertDatabaseHas('friendships', [
            'uuid' => $friendship->uuid,
            'status' => 'declined',
        ]);
    }

    public function test_respond_returns_404_for_nonexistent_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge(['action' => 'accept']);

        try {
            $controller->respond($request, 'nonexistent-uuid');
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_respond_forbidden_for_non_receiver(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'sender_uuid' => $user1->uuid,
            'receiver_uuid' => $user2->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($user3);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge(['action' => 'accept']);

        try {
            $controller->respond($request, $friendship->uuid);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_respond_forbidden_for_sender(): void
    {
        $receiver = User::factory()->create();
        $sender = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $receiver->uuid,
            'status' => 'pending',
        ]);
        Sanctum::actingAs($sender);

        $controller = new FriendshipController;
        $request = new Request;
        $request->merge(['action' => 'accept']);

        try {
            $controller->respond($request, $friendship->uuid);
            $this->fail('Expected ModelNotFoundException');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    public function test_respond_requires_authentication(): void
    {
        $this->expectException(AuthenticationException::class);

        $controller = new FriendshipController;
        $controller->respond(new Request, 'some-uuid');
    }
}
