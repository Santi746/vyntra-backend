<?php

namespace App\Http\Resources;

use App\Models\Friendship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Amistad/Solicitud de Amistad en JSON.
 *
 * @property-read Friendship $resource
 */
class FriendshipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->relationLoaded('sender') && $this->relationLoaded('receiver')) {
            $currentUser = $request->user();
            $friend = $this->sender_uuid === $currentUser?->uuid
                ? $this->receiver
                : $this->sender;

            return [
                'uuid' => (string) $friend->uuid,
                'username' => $friend->username,
                'display_name' => trim(($friend->first_name ?? '').' '.($friend->last_name ?? '')),
                'avatar_url' => $friend->avatar_url,
                'is_online' => (bool) $friend->is_online,
                'friendship_uuid' => (string) $this->uuid,
                'status' => $this->status,
            ];
        }

        return [
            'uuid' => (string) $this->uuid,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'sender' => new UserResource($this->whenLoaded('sender')),
            'receiver' => new UserResource($this->whenLoaded('receiver')),
        ];
    }
}
