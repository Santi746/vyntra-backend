<?php

namespace Tests\Feature;

use App\Models\ClubCategory;
use App\Models\ClubChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubChannelControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_channel_factory_creates_valid_channel(): void
    {
        $category = ClubCategory::factory()->create();
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);

        $this->assertNotNull($channel->uuid);
        $this->assertNotNull($channel->name);
        $this->assertEquals($category->uuid, $channel->category_uuid);
    }

    public function test_channel_belongs_to_category(): void
    {
        $category = ClubCategory::factory()->create();
        $channel = ClubChannel::factory()->create(['category_uuid' => $category->uuid]);

        $this->assertTrue(method_exists($channel, 'category'));
        $this->assertEquals($category->uuid, $channel->category->uuid);
    }

    public function test_channel_casts_is_private_to_boolean(): void
    {
        $channel = ClubChannel::factory()->create(['is_private' => true]);

        $this->assertTrue($channel->is_private);
    }

    public function test_channel_casts_sort_order_to_integer(): void
    {
        $channel = ClubChannel::factory()->create(['sort_order' => 5]);

        $this->assertIsInt($channel->sort_order);
    }

    public function test_channel_soft_deletes(): void
    {
        $channel = ClubChannel::factory()->create();
        $channelUuid = $channel->uuid;

        $channel->delete();

        $this->assertSoftDeleted('club_channels', ['uuid' => $channelUuid]);
    }

    public function test_channel_type_is_valid(): void
    {
        $textChannel = ClubChannel::factory()->create(['type' => 'text']);
        $voiceChannel = ClubChannel::factory()->create(['type' => 'voice']);

        $this->assertEquals('text', $textChannel->type);
        $this->assertEquals('voice', $voiceChannel->type);
    }
}
