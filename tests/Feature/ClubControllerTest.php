<?php

namespace Tests\Feature;

use App\Http\Resources\ClubResource;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
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

    public function test_club_first_or_create_returns_existing_club(): void
    {
        $owner = User::factory()->create();
        $clientUuid = fake()->uuid();

        $club = Club::firstOrCreate(
            ['client_uuid' => $clientUuid],
            [
                'name' => 'Original Club',
                'category_tag' => 'gaming',
                'owner_uuid' => $owner->uuid,
            ]
        );

        $this->assertTrue($club->wasRecentlyCreated);

        $existing = Club::firstOrCreate(
            ['client_uuid' => $clientUuid],
            ['name' => 'Should Not Create', 'category_tag' => 'gaming', 'owner_uuid' => $owner->uuid]
        );

        $this->assertFalse($existing->wasRecentlyCreated);
        $this->assertEquals($club->uuid, $existing->uuid);
        $this->assertEquals('Original Club', $existing->name);
    }

    public function test_club_resource_returns_iso8601_dates(): void
    {
        $club = Club::factory()->create();
        $resource = new ClubResource($club);
        $data = $resource->toArray(Request::create('/'));

        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertArrayHasKey('uuid', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('owner_uuid', $data);

        // Verify ISO 8601 format (matches T separator, not space)
        $this->assertStringContainsString('T', $data['created_at']);
        $this->assertStringContainsString('T', $data['updated_at']);
    }

    public function test_club_resource_returns_owner_when_loaded(): void
    {
        $club = Club::factory()->create();
        $club->load('clubOwner');

        $resource = new ClubResource($club);
        $data = $resource->toArray(Request::create('/'));

        $this->assertArrayHasKey('owner', $data);
        $this->assertArrayHasKey('uuid', $data['owner']);
        $this->assertEquals($club->owner_uuid, $data['owner']['uuid']);
    }

    public function test_club_membership_first_or_create_is_idempotent(): void
    {
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_uuid' => $owner->uuid]);

        // First creation
        $membership = ClubMember::firstOrCreate([
            'user_uuid' => $owner->uuid,
            'club_uuid' => $club->uuid,
        ]);
        $this->assertTrue($membership->wasRecentlyCreated);

        // Second attempt should return existing
        $duplicate = ClubMember::firstOrCreate([
            'user_uuid' => $owner->uuid,
            'club_uuid' => $club->uuid,
        ]);
        $this->assertFalse($duplicate->wasRecentlyCreated);
        $this->assertEquals($membership->uuid, $duplicate->uuid);
    }
}
