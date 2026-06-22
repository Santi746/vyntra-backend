<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use App\Http\Resources\ClubChannelResource;
use App\Models\ClubChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelCreated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly ClubChannel $channel,
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
            't' => 'CHANNEL_CREATE',
            'd' => (new ClubChannelResource($this->channel))->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
