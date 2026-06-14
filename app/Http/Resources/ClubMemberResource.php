<?php

namespace App\Http\Resources;

use App\Models\ClubMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Miembro de Club en JSON.
 *
 * @property-read ClubMember $resource
 */
class ClubMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'uuid' => (string) $this->uuid,
            'user_uuid' => (string) $this->user_uuid,
            'club_uuid' => (string) $this->club_uuid,
            'joined_at' => $this->created_at?->toIso8601String(),

            // Campos enriquecidos desde la relación user
            'username' => $user?->username,
            'display_name' => $user
                ? trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
                : null,
            'avatar_url' => $user?->avatar_url,
            'is_online' => (bool) ($user?->is_online ?? false),

            // Array de UUIDs de roles (desde la pivote club_member_roles)
            'roles_ids' => $this->whenLoaded('roles', function () {
                return $this->roles->pluck('uuid');
            }),

            // Objeto user anidado (opcional, según cuando se cargue)
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
