<?php

namespace App\Policies;

use App\Models\DmConversation;
use App\Models\User;

/**
 * Permisos sobre conversaciones DM. Solo los dos participantes
 * pueden ver la conversación y enviar mensajes.
 */
class DmConversationPolicy
{
    public function view(User $user, DmConversation $dm): bool
    {
        return $dm->user_one_uuid === $user->uuid
            || $dm->user_two_uuid === $user->uuid;
    }

    // El método create de DmConversationPolicy autoriza enviar mensajes
    // dentro de una conversación existente, no crear la conversación misma.
    public function create(User $user, DmConversation $dm): bool
    {
        return $dm->user_one_uuid === $user->uuid
            || $dm->user_two_uuid === $user->uuid;
    }
}
