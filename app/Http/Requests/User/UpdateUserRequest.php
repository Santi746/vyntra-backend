<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * UpdateUserRequest
 * 
 * Gestiona la validación para la actualización parcial del perfil y credenciales del usuario autenticado.
 * 
 * @package App\Http\Requests\User
 * @method string method() HTTP PATCH
 */
class UpdateUserRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * Permitido para cualquier usuario autenticado, que solo puede actualizar su propio perfil.
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
            'username' => ['sometimes', 'string', 'max:50'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->uuid, 'uuid')],
            'first_name' => ['sometimes', 'string', 'max:50'],
            'last_name' => ['sometimes', 'string', 'max:50'],
            'bio' => ['sometimes', 'string', 'max:500', 'nullable'],
            'avatar_url' => ['sometimes', 'url', 'nullable'],
            'banner_url' => ['sometimes', 'url', 'nullable'],
            'location' => ['sometimes', 'string', 'max:100', 'nullable'],
            'current_password' => ['required_with:new_password', 'string'],
            'new_password' => ['sometimes', 'string', 'min:8'],
        ];
    }
}
