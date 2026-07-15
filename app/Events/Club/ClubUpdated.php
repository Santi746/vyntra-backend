<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use App\Http\Resources\ClubResource;
use App\Models\Club;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClubUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly Club $club
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('club.'.$this->club->uuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'CLUB_UPDATE',
            'd' => (new ClubResource($this->club))->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
