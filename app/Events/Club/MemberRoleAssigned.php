<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;

class MemberRoleAssigned implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName;

    public function __construct(
        public readonly string $clubUuid,
        public readonly string $userUuid,
        public readonly string $roleUuid
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
            't' => 'MEMBER_ROLE_ASSIGNED',
            'd' => [
                'club_uuid' => $this->clubUuid,
                'user_uuid' => $this->userUuid,
                'role_uuid' => $this->roleUuid,
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
