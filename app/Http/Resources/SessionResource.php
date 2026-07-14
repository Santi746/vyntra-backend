<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Resource para formatear un Token de Sesión (Sanctum) en JSON.
 *
 * @property-read PersonalAccessToken $resource
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'device_name' => $this->name,
            'is_current' => $request->user()?->currentAccessToken()?->id === $this->id,
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
