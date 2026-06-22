<?php

namespace App\Events\Dm;

use App\Events\HasBroadcastName;
use App\Models\DmMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DmMessageUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly DmMessage $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('dm-conversation.'.$this->message->dm_conversation_uuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'DM_MESSAGE_UPDATE',
            'd' => [
                'uuid' => (string) $this->message->uuid,
                'content' => $this->message->content,
                'updated_at' => $this->message->updated_at->toIso8601String(),
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
