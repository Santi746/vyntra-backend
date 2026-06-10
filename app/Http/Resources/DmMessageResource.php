<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Mensaje Directo en JSON.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\DmMessage $resource
 */
class DmMessageResource extends JsonResource
{
    /**
     * Transforma el mensaje directo en un array JSON para la API.
     *
     * @param Request $request Petición HTTP
     * @return array<string, mixed> Datos del mensaje directo
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->client_uuid,
            'dm_conversation_uuid' => $this->dm_conversation_uuid,
            'sender_uuid' => $this->sender_uuid,
            'content' => $this->content,
            'status' => $this->status,
            'parent_message_uuid' => $this->parent_message_uuid,
            'user' => new UserResource($this->whenLoaded('sender')),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
