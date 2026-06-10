<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Notificación en JSON.
 *
 * El campo `data` es un JSON opaco que el frontend interpreta según el `type`.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\Notification $resource
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type,
            'is_read' => (bool) $this->is_read,
            'data' => $this->data,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
