<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AssignClubRoleRequest
 * 
 * Gestiona la validación para la asignación de un rol de club a un miembro específico.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP POST
 */
class AssignClubRoleRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el controlador se verificará si el usuario autenticado tiene permisos de 'manage_roles'.
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => ['sometimes', 'uuid'], // Opcional (sometimes) porque la asociación es una relación pivot Many-to-Many idempotente (sync) y no requiere deduplicación estricta
            'role_uuid' => ['required', 'uuid', 'exists:club_roles,uuid'],
        ];
    }
}
