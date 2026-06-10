<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Mensaje de Canal en JSON.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\ChannelMessage $resource
 */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->client_uuid,
            // Nota de naming: la columna en BD es `club_channel_uuid` (FK), pero la API
            // expone `channel_uuid` para mantener consistencia con el contrato del frontend
            // y simplicidad en el JS. La migración y el modelo siguen usando club_channel_uuid.
            'channel_uuid' => $this->club_channel_uuid,
            'sender_uuid' => $this->sender_uuid,
            'content' => $this->content,
            'status' => $this->status,
            'parent_message_uuid' => $this->parent_message_uuid,
            'user' => new UserResource($this->whenLoaded('sender')), // Si se envio el sender con with() en el controller.
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
