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
     * Normaliza email y username a minúsculas antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
        if ($this->has('username')) {
            $this->merge([
                'username' => strtolower($this->username),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:50', 'unique:users,username', 'regex:/^[^<>]+$/'],
            'first_name' => ['required', 'string', 'max:50', 'regex:/^[^<>]+$/'],
            'last_name' => ['required', 'string', 'max:50', 'regex:/^[^<>]+$/'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
