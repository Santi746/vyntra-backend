<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\Club;
use App\Models\User;

class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Club $club): bool
    {
        return $club->members()->where('user_uuid', $user->uuid)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::MANAGE_CLUB);
    }

    public function delete(User $user, Club $club): bool
    {
        return $club->owner_uuid === $user->uuid;
    }

    public function manageRoles(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::MANAGE_ROLES);
    }
}
