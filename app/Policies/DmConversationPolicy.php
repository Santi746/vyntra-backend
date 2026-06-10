<?php

namespace App\Policies;

use App\Models\DmConversation;
use App\Models\User;

class DmConversationPolicy
{
    public function view(User $user, DmConversation $dm): bool
    {
        return $dm->user_one_uuid === $user->uuid
            || $dm->user_two_uuid === $user->uuid;
    }

    public function create(User $user, DmConversation $dm): bool
    {
        return $dm->user_one_uuid === $user->uuid
            || $dm->user_two_uuid === $user->uuid;
    }
}
