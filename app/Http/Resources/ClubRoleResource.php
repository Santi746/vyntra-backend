<?php

namespace App\Http\Resources;

use App\Models\ClubRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Rol de Club en JSON.
 *
 * @property-read ClubRole $resource
 */
class ClubRoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => (string) $this->uuid,
            'club_uuid' => (string) $this->club_uuid,
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
