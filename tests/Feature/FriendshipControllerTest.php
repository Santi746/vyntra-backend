<?php

namespace Tests\Feature;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FriendshipControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_accepted_friendships_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        Friendship::factory()->create([
            'sender_uuid' => $user->uuid,
            'receiver_uuid' => $friend->uuid,
            'status' => 'accepted',
        ]);
        $this->actingAs($user);

        $response = $this->getJson('/api/user/friends');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
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
        $this->actingAs($user);

        $response = $this->getJson('/api/user/friends');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/user/friends')->assertStatus(401);
    }

    public function test_pending_returns_pending_requests_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $sender = User::factory()->create();
        Friendship::factory()->create([
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $user->uuid,
            'status' => 'pending',
        ]);
        $this->actingAs($user);

        $response = $this->getJson('/api/user/friend-requests');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta']);
    }

    public function test_pending_returns_only_received_requests(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Friendship::factory()->create([
            'sender_uuid' => $user->uuid,
            'receiver_uuid' => $otherUser->uuid,
            'status' => 'pending',
        ]);
        Friendship::factory()->create([
            'sender_uuid' => $otherUser->uuid,
            'receiver_uuid' => $user->uuid,
            'status' => 'pending',
        ]);
        $this->actingAs($user);

        $response = $this->getJson('/api/user/friend-requests');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_pending_requires_authentication(): void
    {
        $this->getJson('/api/user/friend-requests')->assertStatus(401);
    }

    public function test_store_sends_friendship_request_and_returns_201(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $this->actingAs($sender);

        $response = $this->postJson('/api/user/friend-requests', [
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => $receiver->uuid,
        ]);

        $response->assertStatus(201);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('friendships', [
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $receiver->uuid,
            'status' => 'pending',
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_request(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $this->actingAs($sender);

        $payload = [
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => $receiver->uuid,
        ];

        $this->postJson('/api/user/friend-requests', $payload)->assertStatus(201);

        $this->postJson('/api/user/friend-requests', $payload)->assertStatus(200);

        $this->assertEquals(1, Friendship::where('sender_uuid', $sender->uuid)
            ->where('receiver_uuid', $receiver->uuid)
            ->count());
    }

    public function test_store_prevents_self_friendship(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/user/friend-requests', [
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => $user->uuid,
        ])->assertStatus(422);
    }

    public function test_store_requires_valid_receiver(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/user/friend-requests', [
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => 'nonexistent-uuid',
        ])->assertStatus(422);
    }

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/user/friend-requests', [
            'client_uuid' => Str::uuid()->toString(),
            'receiver_uuid' => 'some-uuid',
        ])->assertStatus(401);
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
        $this->actingAs($receiver);

        $this->patchJson('/api/user/friend-requests/'.$friendship->uuid, [
            'action' => 'accept',
        ])->assertStatus(200);

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
        $this->actingAs($receiver);

        $this->patchJson('/api/user/friend-requests/'.$friendship->uuid, [
            'action' => 'decline',
        ])->assertStatus(200);

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
        $this->actingAs($receiver);

        $this->patchJson('/api/user/friend-requests/'.$friendship->uuid, [
            'action' => 'reject',
        ])->assertStatus(200);

        $this->assertDatabaseHas('friendships', [
            'uuid' => $friendship->uuid,
            'status' => 'declined',
        ]);
    }

    public function test_respond_returns_404_for_nonexistent_request(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->patchJson('/api/user/friend-requests/nonexistent-uuid', [
            'action' => 'accept',
        ])->assertStatus(404);
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
        $this->actingAs($user3);

        $this->patchJson('/api/user/friend-requests/'.$friendship->uuid, [
            'action' => 'accept',
        ])->assertStatus(404);
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
        $this->actingAs($sender);

        $this->patchJson('/api/user/friend-requests/'.$friendship->uuid, [
            'action' => 'accept',
        ])->assertStatus(404);
    }

    public function test_respond_requires_authentication(): void
    {
        $this->patchJson('/api/user/friend-requests/00000000-0000-0000-0000-000000000000', [
            'action' => 'accept',
        ])->assertStatus(401);
    }
}
