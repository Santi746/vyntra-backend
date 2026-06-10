<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_role_factory_creates_valid_role(): void
    {
        $club = Club::factory()->create();
        $role = ClubRole::factory()->create(['club_uuid' => $club->uuid]);

        $this->assertNotNull($role->uuid);
        $this->assertNotNull($role->name);
        $this->assertEquals($club->uuid, $role->club_uuid);
    }

    public function test_role_belongs_to_club(): void
    {
        $club = Club::factory()->create();
        $role = ClubRole::factory()->create(['club_uuid' => $club->uuid]);

        $this->assertTrue(method_exists($role, 'club'));
        $this->assertEquals($club->uuid, $role->club->uuid);
    }

    public function test_role_has_many_members(): void
    {
        $role = ClubRole::factory()->create();

        $this->assertTrue(method_exists($role, 'members'));
    }

    public function test_role_casts_is_fixed_to_boolean(): void
    {
        $role = ClubRole::factory()->create(['is_fixed' => true]);

        $this->assertTrue($role->is_fixed);
    }

    public function test_role_casts_sort_order_to_integer(): void
    {
        $role = ClubRole::factory()->create(['sort_order' => 5]);

        $this->assertIsInt($role->sort_order);
    }

    public function test_role_casts_permissions_to_integer(): void
    {
        $role = ClubRole::factory()->create(['permissions' => 255]);

        $this->assertIsInt($role->permissions);
    }

    public function test_role_soft_deletes(): void
    {
        $role = ClubRole::factory()->create();
        $roleUuid = $role->uuid;

        $role->delete();

        $this->assertSoftDeleted('club_roles', ['uuid' => $roleUuid]);
    }
}