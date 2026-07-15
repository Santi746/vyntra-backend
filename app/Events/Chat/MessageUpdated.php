<?php

namespace App\Events\Chat;

use App\Events\HasBroadcastName;
use App\Models\ChannelMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly ChannelMessage $message
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel.'.$this->message->club_channel_uuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'MESSAGE_UPDATE',
            'd' => [
                'uuid' => (string) $this->message->uuid,
                'content' => $this->message->content,
                'is_edited' => true,
                'updated_at' => $this->message->updated_at->toIso8601String(),
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
