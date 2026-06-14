<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para el registro/creación de un nuevo usuario en la plataforma.
 *
 * @method string method() HTTP POST
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara los datos para la validación.
     * Normaliza el correo electrónico convirtiéndolo a minúsculas antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50'],
            'user_tag' => ['required', 'string', 'max:50', 'unique:users,user_tag'],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
