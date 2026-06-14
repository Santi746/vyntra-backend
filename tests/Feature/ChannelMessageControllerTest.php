<?php

namespace Tests\Feature;

use App\Models\ChannelMessage;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubChannel;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChannelMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setupMemberInClub(User $user, Club $club): ClubMember
    {
        return ClubMember::factory()->create([
            'user_uuid' => $user->uuid,
            'club_uuid' => $club->uuid,
        ]);
    }

    public function test_index_returns_messages_with_cursor_pagination(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);
        $this->setupMemberInClub($user, $club);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson("/api/channels/{$channel->uuid}/messages");

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'meta' => ['next_cursor', 'per_page']]);
    }

    public function test_index_returns_403_for_non_member(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $otherUser->uuid]);
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson("/api/channels/{$channel->uuid}/messages");

        $response->assertStatus(403);
    }

    public function test_store_creates_new_message_and_returns_201(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);
        $this->setupMemberInClub($user, $club);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/channels/{$channel->uuid}/messages", [
            'content' => 'Hello, world!',
            'client_uuid' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(201);
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('channel_messages', [
            'club_channel_uuid' => $channel->uuid,
            'sender_uuid' => $user->uuid,
            'content' => 'Hello, world!',
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_client_uuid(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);
        $this->setupMemberInClub($user, $club);
        $clientUuid = Str::uuid()->toString();

        $this->actingAs($user, 'sanctum');

        $this->postJson("/api/channels/{$channel->uuid}/messages", [
            'content' => 'First',
            'client_uuid' => $clientUuid,
        ])->assertStatus(201);

        $response = $this->postJson("/api/channels/{$channel->uuid}/messages", [
            'content' => 'Second',
            'client_uuid' => $clientUuid,
        ]);

        $response->assertStatus(200);
    }

    public function test_store_requires_valid_data(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);
        $this->setupMemberInClub($user, $club);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/channels/{$channel->uuid}/messages", [
            'client_uuid' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['content']);
    }

    public function test_store_forbidden_for_non_member(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $otherUser->uuid]);
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/channels/{$channel->uuid}/messages", [
            'content' => 'Should not work',
            'client_uuid' => Str::uuid()->toString(),
        ]);

        $response->assertStatus(403);
    }

    public function test_store_with_reply_creates_thread_message(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $user->uuid]);
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);
        $this->setupMemberInClub($user, $club);
        $parent = ChannelMessage::factory()->create([
            'club_channel_uuid' => $channel->uuid,
            'sender_uuid' => $user->uuid,
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/channels/{$channel->uuid}/messages", [
            'content' => 'This is a reply',
            'client_uuid' => Str::uuid()->toString(),
            'parent_message_uuid' => $parent->uuid,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('channel_messages', [
            'parent_message_uuid' => $parent->uuid,
            'content' => 'This is a reply',
        ]);
    }
}
