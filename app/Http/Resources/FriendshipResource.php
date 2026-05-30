<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->relationLoaded('friend')) {
            $friend = $this->friend;
            return [
                'uuid' => $friend->uuid,
                'username' => $friend->username,
                'display_name' => trim(($friend->first_name ?? '') . ' ' . ($friend->last_name ?? '')),
                'avatar_url' => $friend->avatar_url,
                'is_online' => (bool) $friend->is_online,
                'friendship_uuid' => $this->uuid,
                'status' => $this->status,
            ];
        }

        return [
            'uuid' => $this->uuid,
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'sender' => new UserResource($this->whenLoaded('sender')),
            'recipient' => new UserResource($this->whenLoaded('recipient')),
        ];
    }
}
