<?php

namespace App\Events\User;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;

class FriendshipStatusChanged implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName;

    public function __construct(
        public readonly string $friendshipUuid,
        public readonly string $senderUuid,
        public readonly string $receiverUuid,
        public readonly string $status,
        public readonly string $action
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->senderUuid),
            new PrivateChannel('user.'.$this->receiverUuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'FRIENDSHIP_STATUS_CHANGED',
            'd' => [
                'uuid' => $this->friendshipUuid,
                'sender_uuid' => $this->senderUuid,
                'receiver_uuid' => $this->receiverUuid,
                'status' => $this->status,
                'action' => $this->action,
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
