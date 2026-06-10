<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para formatear un Miembro de Club en JSON.
 *
 * Este Resource NO es un mapeo 1:1 de la tabla `club_members`.
 * Expone una vista enriquecida del usuario-miembro con los datos
 * que consume el frontend (Next.js) en:
 *
 *   - Sidebar de miembros
 *   - useClubMembers (useInfiniteQuery con paginación por cursor)
 *   - useClubMembership (vía useCheckPermission para permisos)
 *
 * El frontend espera recibir:
 *   {
 *     uuid, username, display_name, avatar_url, is_online,
 *     roles_ids: [...],   // o role_uuids según el contrato
 *     user: { ... }       // anidado
 *   }
 *
 * El campo `roles_ids` viene de la tabla pivote `club_member_roles`
 * mediante la relación `roles()` del modelo.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\ClubMember $resource
 */
class ClubMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->user;

        return [
            'uuid' => $this->uuid,
            'user_uuid' => $this->user_uuid,
            'club_uuid' => $this->club_uuid,
            'joined_at' => $this->created_at?->toIso8601String(),

            // Campos enriquecidos desde la relación user
            'username' => $user?->username,
            'display_name' => $user
                ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
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
