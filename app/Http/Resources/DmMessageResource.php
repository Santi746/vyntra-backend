<?php

namespace App\Http\Resources;

use App\Models\DmMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Mensaje Directo en JSON.
 *
 * @property-read DmMessage $resource
 */
class DmMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'client_uuid' => (string) $this->client_uuid,
            'dm_conversation_uuid' => (string) $this->dm_conversation_uuid,
            'sender_uuid' => (string) $this->sender_uuid,
            'content' => $this->content,
            'status' => $this->status,
            'parent_message_uuid' => (string) $this->parent_message_uuid,
            'user' => new UserResource($this->whenLoaded('sender')),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
