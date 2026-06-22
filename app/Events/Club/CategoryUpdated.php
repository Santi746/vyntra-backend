<?php

namespace App\Events\Club;

use App\Events\HasBroadcastName;
use App\Http\Resources\ClubCategoryResource;
use App\Models\ClubCategory;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CategoryUpdated implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, HasBroadcastName, SerializesModels;

    public function __construct(
        public readonly ClubCategory $category
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('club.'.$this->category->club_uuid),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            't' => 'CATEGORY_UPDATE',
            'd' => (new ClubCategoryResource($this->category))->resolve(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
