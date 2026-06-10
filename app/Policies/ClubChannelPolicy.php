<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\ClubChannel;
use App\Models\User;

class ClubChannelPolicy
{
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
