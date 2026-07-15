<?php

use App\Models\Club;
use App\Models\ClubChannel;
use App\Models\DmConversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal personal del usuario
Broadcast::channel('user.{userUuid}', function (User $user, string $userUuid) {
    return (string) $user->uuid === $userUuid;
});

// Canal de conversación directa
Broadcast::channel('dm-conversation.{dmUuid}', function (User $user, string $dmUuid) {
    $conversation = DmConversation::find($dmUuid);

    if (! $conversation) {
        return false;
    }

    return in_array((string) $user->uuid, [
        (string) $conversation->user_one_uuid,
        (string) $conversation->user_two_uuid,
    ], true);
});

// Canal de mensajes de club
Broadcast::channel('channel.{channelUuid}', function (User $user, string $channelUuid) {
    $channel = ClubChannel::find($channelUuid);

    if (! $channel) {
        return false;
    }

    // Verificar membresía del usuario en el club
    return $channel->category->club->members()
        ->where('user_uuid', $user->uuid)
        ->exists();
});

// Canal de club (estructura y miembros)
Broadcast::channel('club.{clubUuid}', function (User $user, string $clubUuid) {
    $club = Club::find($clubUuid);

    if (! $club) {
        return false;
    }

    return $club->members()
        ->where('user_uuid', $user->uuid)
        ->exists();
});
