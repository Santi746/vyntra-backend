<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\Club;
use App\Models\User;

/**
 * Permisos sobre miembros del club. Solo miembros pueden ver el roster;
 * KICK_MEMBERS para expulsar.
 */
class ClubMemberPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->members()->where('user_uuid', $user->uuid)->exists();
    }

    public function delete(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::KICK_MEMBERS);
    }
}
