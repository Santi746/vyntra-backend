<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClubMemberFactory extends Factory
{
    protected $model = ClubMember::class;

    public function definition(): array
    {
        return [
            'user_uuid' => User::factory(),
            'club_uuid' => Club::factory(),
        ];
    }
}
