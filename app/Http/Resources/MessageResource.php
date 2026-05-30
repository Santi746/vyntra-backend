<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'client_uuid' => $this->client_uuid,
            'channel_uuid' => $this->channel_uuid,
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
