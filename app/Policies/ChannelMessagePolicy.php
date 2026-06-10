<?php

namespace App\Policies;

use App\Enums\ClubPermission;
use App\Models\ChannelMessage;
use App\Models\ClubChannel;
use App\Models\ClubMember;
use App\Models\User;

class ChannelMessagePolicy
{
    public function viewAny(User $user, ClubChannel $channel): bool
    {
        return ClubMember::where('user_uuid', $user->uuid)
            ->where('club_uuid', $channel->category->club_uuid)
            ->exists();
    }

    public function create(User $user, ClubChannel $channel): bool
    {
        return $user->hasClubPermission(
            $channel->category->club,
            ClubPermission::SEND_MESSAGES
        );
    }
}
