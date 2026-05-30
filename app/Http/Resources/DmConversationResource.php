<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DmConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'participant' => new UserResource($this->when(
                $this->relationLoaded('user1') || $this->relationLoaded('user2'), // Si esta relacion cargo aplica la funcion ...
                function () use ($request) { // esta 
                    $currentUser = $request->user();
                    if ($currentUser && $this->user1_uuid === $currentUser->uuid) {
                        return $this->user2;
                    }
                    return $this->user1;
                }
            )),
            'last_message' => new DmMessageResource($this->whenLoaded('lastMessage')),
        ];
    }
}
