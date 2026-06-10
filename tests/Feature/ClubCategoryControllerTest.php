<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubCategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_category_factory_creates_valid_category(): void
    {
        $club = Club::factory()->create();
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);

        $this->assertNotNull($category->uuid);
        $this->assertNotNull($category->name);
        $this->assertEquals($club->uuid, $category->club_uuid);
    }

    public function test_category_belongs_to_club(): void
    {
        $club = Club::factory()->create();
        $category = ClubCategory::factory()->create(['club_uuid' => $club->uuid]);

        $this->assertTrue(method_exists($category, 'club'));
        $this->assertEquals($club->uuid, $category->club->uuid);
    }

    public function test_category_has_many_channels(): void
    {
        $category = ClubCategory::factory()->create();

        $this->assertTrue(method_exists($category, 'channels'));
    }

    public function test_category_soft_deletes(): void
    {
        $category = ClubCategory::factory()->create();
        $categoryUuid = $category->uuid;

        $category->delete();

        $this->assertSoftDeleted('club_categories', ['uuid' => $categoryUuid]);
    }

    public function test_category_casts_is_private_to_boolean(): void
    {
        $category = ClubCategory::factory()->create(['is_private' => true]);

        $this->assertTrue($category->is_private);
    }

    public function test_category_casts_sort_order_to_integer(): void
    {
        $category = ClubCategory::factory()->create(['sort_order' => 5]);

        $this->assertIsInt($category->sort_order);
    }
}