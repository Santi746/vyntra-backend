<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Conversación DM en JSON.
 *
 * Devuelve el participante (el "otro" usuario) calculado desde el usuario autenticado.
 * Si no hay usuario autenticado, devuelve userOne como fallback.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\DmConversation $resource
 */
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
