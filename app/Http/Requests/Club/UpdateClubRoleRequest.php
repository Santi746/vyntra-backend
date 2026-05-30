<?php

namespace App\Http\Requests\Club;

use Illuminate\Foundation\Http\FormRequest;

/**
 * UpdateClubRoleRequest
 * 
 * Gestiona la validación para la actualización parcial de un rol de club existente.
 * 
 * @package App\Http\Requests\Club
 * @method string method() HTTP PATCH
 */
class UpdateClubRoleRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el controlador se verificará si el miembro tiene permisos de 'manage_roles'.
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
            // client_uuid removido: las peticiones PATCH son inherentemente idempotentes y modifican recursos existentes.
            'name' => ['sometimes', 'string', 'max:50'],
            'color' => ['sometimes', 'string', 'regex:/^#[a-fA-F0-9]{6}$/i'],
            'permissions' => ['sometimes', 'array'],
            'permissions.manage_channels' => ['sometimes', 'boolean'],
            'permissions.manage_roles' => ['sometimes', 'boolean'],
            'permissions.manage_members' => ['sometimes', 'boolean'],
            'permissions.send_messages' => ['sometimes', 'boolean'],
            'permissions.manage_club' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Obtiene los mensajes personalizados para los errores de validación.
     * 
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'color.regex' => 'El color debe ser un valor hexadecimal válido de 6 caracteres (ej. #FFFFFF).',
        ];
    }
}
