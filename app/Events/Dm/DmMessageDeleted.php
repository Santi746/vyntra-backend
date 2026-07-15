<?php

namespace App\Events\Dm;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DmMessageDeleted implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly string $messageUuid,
        public readonly string $conversationUuid
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dm-conversation.'.$this->conversationUuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'DM_MESSAGE_DELETE',
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
