<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryDeleted implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly string $categoryUuid,
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
            't' => 'CATEGORY_DELETE',
            'd' => [
                'uuid' => $this->categoryUuid,
                'action' => 'delete',
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
