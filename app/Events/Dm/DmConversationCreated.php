<?php

namespace App\Events\Dm;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;

class DmConversationCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName;

    public function __construct(
        public readonly string $conversationUuid,
        public readonly string $senderUuid,
        public readonly string $receiverUuid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->receiverUuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'DM_CONVERSATION_CREATED',
            'd' => [
                'conversation_uuid' => $this->conversationUuid,
                'sender_uuid' => $this->senderUuid,
                'receiver_uuid' => $this->receiverUuid,
                'action' => 'created',
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
