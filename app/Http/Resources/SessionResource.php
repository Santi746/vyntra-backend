<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Token de Sesión (Sanctum) en JSON.
 *
 * Mapea un PersonalAccessToken a la vista de "sesión activa" que consume
 * el frontend en la página de configuración de cuenta.
 *
 * @package App\Http\Resources
 *
 * @property-read \Laravel\Sanctum\PersonalAccessToken $resource
 */
class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->ulid ?? (string) $this->id,
            'os' => $this->os,
            'browser' => $this->browser,
            'ip' => $this->ip,
            'location' => $this->location,
            'is_current' => $request->user()?->currentAccessToken()?->id === $this->id,
            'type' => $this->name,
        ];
    }
}
