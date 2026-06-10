<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\ClubCategory;
use App\Models\User;

class ClubCategoryPolicy
{
    public function update(User $user, ClubCategory $category): bool
    {
        return $user->hasClubPermission($category->club, ClubPermission::MANAGE_CHANNELS);
    }

    public function delete(User $user, ClubCategory $category): bool
    {
        return $user->hasClubPermission($category->club, ClubPermission::MANAGE_CHANNELS);
    }
}
