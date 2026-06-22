<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use App\Http\Resources\ClubRoleResource;
use App\Models\ClubRole;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RoleCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly ClubRole $role
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('club.'.$this->role->club_uuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'ROLE_CREATE',
            'd' => (new ClubRoleResource($this->role))->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
