<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelDeleted implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly string $channelUuid,
        public readonly string $clubUuid
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
            't' => 'CHANNEL_DELETE',
            'd' => [
                'uuid' => $this->channelUuid,
                'action' => 'delete',
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
