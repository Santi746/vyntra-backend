<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Permisos sobre notificaciones. Solo el dueño puede marcar como leída.
 */
class NotificationPolicy
{
    public function update(User $user, Notification $notification): bool
    {
        return $notification->user_uuid === $user->uuid;
    }
}
