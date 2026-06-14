<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear una Notificación en JSON.
 *
 * @property-read Notification $resource
 */
class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'type' => $this->type,
            'is_read' => (bool) $this->is_read,
            'data' => $this->data,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
