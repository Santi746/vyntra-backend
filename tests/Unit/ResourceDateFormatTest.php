<?php

namespace Tests\Unit;

use App\Http\Resources\ClubCategoryResource;
use App\Http\Resources\ClubChannelResource;
use App\Http\Resources\ClubMemberResource;
use App\Http\Resources\ClubResource;
use App\Http\Resources\DmConversationResource;
use App\Http\Resources\DmMessageResource;
use App\Http\Resources\FriendshipResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\NotificationResource;
use App\Models\ChannelMessage;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubChannel;
use App\Models\ClubMember;
use App\Models\DmConversation;
use App\Models\DmMessage;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ResourceDateFormatTest extends TestCase
{
    use RefreshDatabase;

    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = Request::create('/');
    }

    public function test_club_resource_dates_are_iso8601(): void
    {
        $club = Club::factory()->create();
        $data = (new ClubResource($club))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_club_category_resource_dates_are_iso8601(): void
    {
        $club = Club::factory()->create();
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $data = (new ClubCategoryResource($category))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_club_channel_resource_dates_are_iso8601(): void
    {
        $club = Club::factory()->create();
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);
        $data = (new ClubChannelResource($channel))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_dm_conversation_resource_dates_are_iso8601(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user1->uuid,
            'user_two_uuid' => $user2->uuid,
        ]);
        $conversation->load(['userOne', 'userTwo']);
        $data = (new DmConversationResource($conversation))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_dm_message_resource_dates_are_iso8601(): void
    {
        $user = User::factory()->create();
        $user2 = User::factory()->create();
        $conversation = DmConversation::factory()->create([
            'user_one_uuid' => $user->uuid,
            'user_two_uuid' => $user2->uuid,
        ]);
        $message = DmMessage::factory()->create([
            'dm_conversation_uuid' => $conversation->uuid,
            'sender_uuid' => $user->uuid,
        ]);
        $data = (new DmMessageResource($message))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_channel_message_resource_dates_are_iso8601(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);
        $message = ChannelMessage::factory()->create([
            'club_channel_uuid' => $channel->uuid,
            'sender_uuid' => $user->uuid,
        ]);
        $data = (new MessageResource($message))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_friendship_resource_dates_are_iso8601(): void
    {
        $sender = User::factory()->create();
        $receiver = User::factory()->create();
        $friendship = Friendship::factory()->create([
            'sender_uuid' => $sender->uuid,
            'receiver_uuid' => $receiver->uuid,
            'status' => 'accepted',
        ]);
        $data = (new FriendshipResource($friendship))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_notification_resource_dates_are_iso8601(): void
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_uuid' => $user->uuid]);
        $data = (new NotificationResource($notification))->toArray($this->request);
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_club_member_resource_dates_are_iso8601(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $member = ClubMember::factory()->create([
            'user_uuid' => $user->uuid,
            'club_uuid' => $club->uuid,
        ]);
        $member->load('user');
        $data = (new ClubMemberResource($member))->toArray($this->request);
        $this->assertStringContainsString('T', $data['joined_at']);
        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('username', $data);
    }
}
