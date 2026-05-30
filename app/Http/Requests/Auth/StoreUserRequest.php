<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreUserRequest
 * 
 * Gestiona la validación para el registro/creación de un nuevo usuario en la plataforma.
 * 
 * @package App\Http\Requests\Auth
 * @method string method() HTTP POST
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * Permitido para cualquier invitado que desee registrarse.
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara los datos para la validación.
     * Normaliza el correo electrónico convirtiéndolo a minúsculas antes de validar.
     * 
     * @return void
     */
    protected function prepareForValidation() {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username'   => ['required', 'string', 'max:50'],
            'user_tag'   => ['required', 'string', 'max:50', 'unique:users,user_tag'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name'  => ['required', 'string', 'max:50'],
            'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
