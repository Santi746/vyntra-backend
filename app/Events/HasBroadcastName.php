<?php

namespace App\Events;

trait HasBroadcastName
{
    public function broadcastAs(): string
    {
        return class_basename(static::class);
    }
}
