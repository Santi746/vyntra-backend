<?php

namespace App\Http\Resources;

use App\Models\DmConversation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Conversación DM en JSON.
 *
 * @property-read DmConversation $resource
 */
class DmConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'participant' => new UserResource($this->when(
                $this->relationLoaded('userOne') || $this->relationLoaded('userTwo'),
                function () use ($request) {
                    $currentUser = $request->user();
                    if ($currentUser && $this->user_one_uuid === $currentUser->uuid) {
                        return $this->userTwo;
                    }

                    return $this->userOne;
                }
            )),
            'last_message' => new DmMessageResource($this->whenLoaded('lastMessage')),
        ];
    }
}
