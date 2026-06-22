<?php

namespace App\Events\Chat;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly string $messageUuid,
        public readonly string $channelUuid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel.'.$this->channelUuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'MESSAGE_DELETE',
            'd' => [
                'uuid' => $this->messageUuid,
                'action' => 'delete',
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
