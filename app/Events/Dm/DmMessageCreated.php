<?php

namespace App\Events\Dm;

use App\Events\HasBroadcastName;
use App\Http\Resources\DmMessageResource;
use App\Models\DmMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DmMessageCreated implements ShouldBroadcast, ShouldQueue
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
            't' => 'DM_MESSAGE_CREATE',
            'd' => (new DmMessageResource($this->message))->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
