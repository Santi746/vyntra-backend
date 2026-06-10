<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Amistad/Solicitud de Amistad en JSON.
 *
 * Tiene DOS formas de salida según las relaciones cargadas:
 *  - Si el controller carga `sender` Y `receiver`: devuelve datos centrados en el "amigo"
 *    (forma consumida por el sidebar de amigos y la lista de solicitudes pendientes).
 *  - Si NO carga ambas: devuelve la forma con `sender` y `receiver` anidados.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\Friendship $resource
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
            'receiver' => new UserResource($this->whenLoaded('receiver')),
        ];
    }
}
