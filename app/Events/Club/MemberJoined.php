<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;

class MemberJoined implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName;

    public function __construct(
        public readonly string $clubUuid,
        public readonly string $userUuid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('club.'.$this->clubUuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'MEMBER_JOINED',
            'd' => [
                'club_uuid' => $this->clubUuid,
                'user_uuid' => $this->userUuid,
                'action' => 'joined',
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
