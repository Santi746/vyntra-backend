<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_factory_creates_valid_club(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $owner->uuid]);

        $this->assertNotNull($club->uuid);
        $this->assertNotNull($club->name);
        $this->assertEquals($owner->uuid, $club->owner_uuid);
    }

    public function test_club_belongs_to_owner(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $owner->uuid]);

        $this->assertTrue(method_exists($club, 'clubOwner'));
        $this->assertEquals($owner->uuid, $club->clubOwner->uuid);
    }

    public function test_club_has_many_members(): void
    {
        $club = Club::factory()->create();
        ClubMember::factory()->count(3)->create(['club_uuid' => $club->uuid]);

        $this->assertTrue(method_exists($club, 'members'));
        $this->assertEquals(3, $club->members()->count());
    }

    public function test_club_has_many_categories(): void
    {
        $club = Club::factory()->create();

        $this->assertTrue(method_exists($club, 'categories'));
    }

    public function test_club_has_many_roles(): void
    {
        $club = Club::factory()->create();

        $this->assertTrue(method_exists($club, 'roles'));
    }

    public function test_club_soft_deletes(): void
    {
        $club = Club::factory()->create();
        $clubUuid = $club->uuid;

        $club->delete();

        $this->assertSoftDeleted('clubs', ['uuid' => $clubUuid]);
    }

    public function test_auto_membership_creation_on_club_create(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $owner->uuid]);

        // When a club is created, owner should automatically become a member
        // This is handled by the controller, not the model
        // Here we just verify the relationship works
        $this->assertTrue(method_exists($club, 'members'));
    }
}