<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la asignación de un rol de club a un miembro específico.
 *
 * @method string method() HTTP POST
 */
class AssignClubRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // client_uuid removido: la pivote 'club_member_roles' no tiene columna client_uuid;
            // la deduplicación se garantiza mediante la restricción UNIQUE compuesta (club_member_uuid, role_uuid) de la migración.
            'user_uuid' => ['required', 'uuid', 'exists:users,uuid'],
            'role_uuid' => ['required', 'uuid', 'exists:club_roles,uuid'],
        ];
    }
}
