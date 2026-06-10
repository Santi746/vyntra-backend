<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\Club;
use App\Models\User;

class ClubMemberRolePolicy
{
    public function create(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::MANAGE_ROLES);
    }
}
