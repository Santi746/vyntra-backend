<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\Club;
use App\Models\User;

class ClubMemberPolicy
{
    public function delete(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::KICK_MEMBERS);
    }
}
