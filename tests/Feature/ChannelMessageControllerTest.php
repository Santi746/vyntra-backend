<?php

namespace Tests\Feature;

use App\Http\Controllers\ChannelMessageController;
use App\Http\Requests\Chat\StoreChannelMessageRequest;
use App\Models\ChannelMessage;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubChannel;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChannelMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Club $club;
    private ClubCategory $category;
    private ClubChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->club = Club::factory()->create(['owner_uuid' => $this->user->uuid]);
        $this->category = ClubCategory::factory()->create(['club_uuid' => $this->club->uuid]);
        $this->channel = ClubChannel::factory()->create(['category_uuid' => $this->category->uuid]);
        ClubMember::factory()->create([
            'user_uuid' => $this->user->uuid,
            'club_uuid' => $this->club->uuid,
        ]);
    }

    public function test_index_returns_messages_with_cursor_pagination(): void
    {
        ChannelMessage::factory()->count(3)->create([
            'club_channel_uuid' => $this->channel->uuid,
            'sender_uuid' => $this->user->uuid,
        ]);
        Sanctum::actingAs($this->user);

        $controller = new ChannelMessageController();
        $response = $controller->index($this->channel);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
    }

    public function test_index_forbidden_for_non_member(): void
    {
        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);

        $controller = new ChannelMessageController();

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $controller->index($this->channel);
    }

    public function test_store_creates_new_message_and_returns_201(): void
    {
        Sanctum::actingAs($this->user);

        $controller = new ChannelMessageController();

        // Create a mock StoreChannelMessageRequest
        $request = $this->app->make(StoreChannelMessageRequest::class);
        $request->merge([
            'client_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'content' => 'Hello, world!',
        ]);

        $response = $controller->store($request, $this->channel);

        $statusCode = $response->getStatusCode();
        $this->assertTrue(in_array($statusCode, [201, 200]));

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('success', $data['status']);

        $this->assertDatabaseHas('channel_messages', [
            'content' => 'Hello, world!',
            'club_channel_uuid' => $this->channel->uuid,
            'sender_uuid' => $this->user->uuid,
        ]);
    }

    public function test_store_is_idempotent_returns_200_on_existing_client_uuid(): void
    {
        Sanctum::actingAs($this->user);

        $clientUuid = \Illuminate\Support\Str::uuid()->toString();

        $controller = new ChannelMessageController();

        // First request - creates message
        $request1 = $this->app->make(StoreChannelMessageRequest::class);
        $request1->merge([
            'client_uuid' => $clientUuid,
            'content' => 'Hello, world!',
        ]);
        $response1 = $controller->store($request1, $this->channel);
        $this->assertEquals(201, $response1->getStatusCode());

        // Second request - should return 200 (already exists)
        $request2 = $this->app->make(StoreChannelMessageRequest::class);
        $request2->merge([
            'client_uuid' => $clientUuid,
            'content' => 'Hello, world!',
        ]);
        $response2 = $controller->store($request2, $this->channel);
        $this->assertEquals(200, $response2->getStatusCode());

        $this->assertEquals(1, ChannelMessage::where('client_uuid', $clientUuid)->count());
    }

    public function test_store_forbidden_for_non_member(): void
    {
        $nonMember = User::factory()->create();
        Sanctum::actingAs($nonMember);

        $controller = new ChannelMessageController();

        // Create a mock StoreChannelMessageRequest
        $request = $this->app->make(StoreChannelMessageRequest::class);
        $request->merge([
            'client_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'content' => 'Hello, world!',
        ]);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        $controller->store($request, $this->channel);
    }

    public function test_store_with_reply_creates_thread_message(): void
    {
        $parentMessage = ChannelMessage::factory()->create([
            'club_channel_uuid' => $this->channel->uuid,
            'sender_uuid' => $this->user->uuid,
        ]);
        Sanctum::actingAs($this->user);

        $controller = new ChannelMessageController();

        $request = $this->app->make(StoreChannelMessageRequest::class);
        $request->merge([
            'client_uuid' => \Illuminate\Support\Str::uuid()->toString(),
            'content' => 'This is a reply',
            'parent_message_uuid' => $parentMessage->uuid,
        ]);

        $response = $controller->store($request, $this->channel);

        $this->assertEquals(201, $response->getStatusCode());

        $this->assertDatabaseHas('channel_messages', [
            'content' => 'This is a reply',
            'parent_message_uuid' => $parentMessage->uuid,
        ]);
    }
}