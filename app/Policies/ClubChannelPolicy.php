<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\Club;
use App\Models\ClubChannel;
use App\Models\User;

/**
 * Permisos sobre canales. Solo miembros pueden listar;
 * MANAGE_CHANNELS para crear, editar o eliminar.
 */
class ClubChannelPolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->members()->where('user_uuid', $user->uuid)->exists();
    }

    public function create(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::MANAGE_CHANNELS);
    }

    public function update(User $user, ClubChannel $channel): bool
    {
        return $user->hasClubPermission(
            $channel->category->club,
            ClubPermission::MANAGE_CHANNELS
        );
    }

    public function delete(User $user, ClubChannel $channel): bool
    {
        return $user->hasClubPermission(
            $channel->category->club,
            ClubPermission::MANAGE_CHANNELS
        );
    }
}
