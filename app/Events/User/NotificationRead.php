<?php

namespace App\Events\User;

use App\Events\HasBroadcastName;
use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationRead implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly Notification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->notification->user_uuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'NOTIFICATION_READ',
            'd' => [
                'uuid' => (string) $this->notification->uuid,
                'is_read' => (bool) $this->notification->is_read,
            ],
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
