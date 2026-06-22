<?php

namespace App\Events\Chat;

use App\Events\HasBroadcastName;
use App\Http\Resources\MessageResource;
use App\Models\ChannelMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageCreated implements ShouldBroadcast, ShouldQueue
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
            't' => 'MESSAGE_CREATE',
            'd' => (new MessageResource($this->message))->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
