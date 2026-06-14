<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\Club;
use App\Models\User;

/**
 * Permisos sobre clubes. Solo miembros pueden ver detalles;
 * MANAGE_CLUB para editar; solo el owner puede eliminar.
 *
 * viewPrivateChannels tiene owner/ADMIN bypass via hasClubPermission.
 */
class ClubPolicy
{
    public function view(User $user, Club $club): bool
    {
        return $club->members()->where('user_uuid', $user->uuid)->exists();
    }

    public function viewPrivateChannels(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::VIEW_CHANNELS);
    }

    public function update(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::MANAGE_CLUB);
    }

    // Owner-only: ni MANAGE_CLUB puede borrar el club.
    public function delete(User $user, Club $club): bool
    {
        return $club->owner_uuid === $user->uuid;
    }
}
