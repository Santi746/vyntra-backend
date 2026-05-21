<?php

namespace Database\Factories;

use App\Models\ClubMember;
use App\Models\ClubMemberRole;
use App\Models\ClubRole;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubMemberRoleFactory extends Factory
{
    protected $model = ClubMemberRole::class;

    public function definition(): array
    {
        return [
            'club_member_uuid' => ClubMember::factory(),
            'role_uuid' => ClubRole::factory(),
        ];
    }
}
