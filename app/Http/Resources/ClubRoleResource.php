<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Rol de Club en JSON.
 *
 * El campo `permissions` se almacena y se transmite como UN SOLO integer
 * (bitmask). El frontend usa BigInt() + operaciones bitwise (OR para
 * acumular, AND para verificar) sobre el valor raw. Ver
 * src/shared/constants/permissions.js — los 20+ bits están definidos allí.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\ClubRole $resource
 */
class ClubRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'club_uuid' => $this->club_uuid,
            'name' => $this->name,
            'color' => $this->color,
            'permissions' => (int) $this->permissions,
            'is_fixed' => (bool) $this->is_fixed,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
