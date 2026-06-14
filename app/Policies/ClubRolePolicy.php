<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\Club;
use App\Models\ClubRole;
use App\Models\User;

/**
 * Permisos sobre roles del club. MANAGE_ROLES requerido para crear;
 * outranks() previene privilege escalation (un admin no puede
 * auto-otorgarse permisos superiores ni editar roles de mayor rango).
 */
class ClubRolePolicy
{
    public function viewAny(User $user, Club $club): bool
    {
        return $club->members()->where('user_uuid', $user->uuid)->exists();
    }

    // Previene privilege escalation: owner > ADMINISTRATOR > MANAGE_ROLES > resto.
    private function outranks(User $user, Club $club, ClubRole $target): bool
    {
        if ($club->owner_uuid === $user->uuid) {
            return true;
        }

        $membership = $user->memberships()
            ->where('club_uuid', $club->uuid)
            ->with(['roles' => fn ($q) => $q->select('club_roles.uuid', 'club_roles.permissions')])
            ->first();

        if (! $membership) {
            return false;
        }

        $userBitmask = $membership->roles
            ->reduce(fn ($carry, $role) => $carry | (int) $role->permissions, 0);

        if (($userBitmask & ClubPermission::ADMINISTRATOR) === ClubPermission::ADMINISTRATOR) {
            return true;
        }

        if (($userBitmask & ClubPermission::MANAGE_ROLES) !== ClubPermission::MANAGE_ROLES) {
            return false;
        }

        return ($userBitmask & (int) $target->permissions) === (int) $target->permissions;
    }

    public function create(User $user, Club $club): bool
    {
        return $user->hasClubPermission($club, ClubPermission::MANAGE_ROLES);
    }

    public function update(User $user, Club $club, ClubRole $role): bool
    {
        return $this->outranks($user, $club, $role);
    }

    // is_fixed solo el owner puede tocarlo.
    public function delete(User $user, Club $club, ClubRole $role): bool
    {
        if ($role->is_fixed) {
            return $club->owner_uuid === $user->uuid;
        }

        return $this->outranks($user, $club, $role);
    }
}
