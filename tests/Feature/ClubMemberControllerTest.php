<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_club_member_factory_creates_valid_membership(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();
        $membership = ClubMember::factory()->create([
            'user_uuid' => $user->uuid,
            'club_uuid' => $club->uuid,
        ]);

        $this->assertNotNull($membership->uuid);
        $this->assertEquals($user->uuid, $membership->user_uuid);
        $this->assertEquals($club->uuid, $membership->club_uuid);
    }

    public function test_membership_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $membership = ClubMember::factory()->create(['user_uuid' => $user->uuid]);

        $this->assertTrue(method_exists($membership, 'user'));
        $this->assertEquals($user->uuid, $membership->user->uuid);
    }

    public function test_membership_belongs_to_club(): void
    {
        $club = Club::factory()->create();
        $membership = ClubMember::factory()->create(['club_uuid' => $club->uuid]);

        $this->assertTrue(method_exists($membership, 'club'));
        $this->assertEquals($club->uuid, $membership->club->uuid);
    }

    public function test_membership_has_many_roles(): void
    {
        $membership = ClubMember::factory()->create();

        $this->assertTrue(method_exists($membership, 'roles'));
    }

    public function test_membership_soft_deletes(): void
    {
        $membership = ClubMember::factory()->create();
        $membershipUuid = $membership->uuid;

        $membership->delete();

        $this->assertSoftDeleted('club_members', ['uuid' => $membershipUuid]);
    }

    public function test_user_can_have_multiple_memberships(): void
    {
        $user = User::factory()->create();
        ClubMember::factory()->count(3)->create(['user_uuid' => $user->uuid]);

        $this->assertEquals(3, $user->memberships()->count());
    }

    public function test_club_can_have_multiple_members(): void
    {
        $club = Club::factory()->create();
        ClubMember::factory()->count(5)->create(['club_uuid' => $club->uuid]);

        $this->assertEquals(5, $club->members()->count());
    }
}