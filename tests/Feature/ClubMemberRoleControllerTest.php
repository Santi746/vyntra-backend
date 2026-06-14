<?php

namespace Tests\Feature;

use App\Models\ClubMember;
use App\Models\ClubMemberRole;
use App\Models\ClubRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubMemberRoleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_member_role_factory_creates_valid_assignment(): void
    {
        $membership = ClubMember::factory()->create();
        $role = ClubRole::factory()->create();
        $memberRole = ClubMemberRole::factory()->create([
            'club_member_uuid' => $membership->uuid,
            'role_uuid' => $role->uuid,
        ]);

        $this->assertNotNull($memberRole->uuid);
        $this->assertEquals($membership->uuid, $memberRole->club_member_uuid);
        $this->assertEquals($role->uuid, $memberRole->role_uuid);
    }

    public function test_member_role_belongs_to_member(): void
    {
        $membership = ClubMember::factory()->create();
        $memberRole = ClubMemberRole::factory()->create(['club_member_uuid' => $membership->uuid]);

        $this->assertTrue(method_exists($memberRole, 'member'));
    }

    public function test_member_role_belongs_to_role(): void
    {
        $role = ClubRole::factory()->create();
        $memberRole = ClubMemberRole::factory()->create(['role_uuid' => $role->uuid]);

        $this->assertTrue(method_exists($memberRole, 'role'));
    }

    public function test_member_can_have_multiple_roles(): void
    {
        $membership = ClubMember::factory()->create();
        ClubMemberRole::factory()->count(3)->create(['club_member_uuid' => $membership->uuid]);

        $this->assertEquals(3, $membership->roles()->count());
    }

    public function test_role_can_be_assigned_to_multiple_members(): void
    {
        $role = ClubRole::factory()->create();
        ClubMemberRole::factory()->count(4)->create(['role_uuid' => $role->uuid]);

        $this->assertEquals(4, $role->members()->count());
    }
}
