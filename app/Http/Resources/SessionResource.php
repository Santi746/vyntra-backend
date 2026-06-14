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
            'uuid' => (string) ($this->ulid ?? $this->id),
            'os' => $this->os,
            'browser' => $this->browser,
            'ip' => $this->ip,
            'location' => $this->location,
            'is_current' => $request->user()?->currentAccessToken()?->id === $this->id,
            'type' => $this->name,
        ];
    }
}
