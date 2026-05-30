<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * LoginRequest
 * 
 * Gestiona la validación para el inicio de sesión de usuarios en la plataforma.
 * 
 * @package App\Http\Requests\Auth
 * @method string method() HTTP POST
 */
class LoginRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En este caso, cualquier usuario invitado o autenticado puede intentar iniciar sesión.
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
    protected function prepareForValidation()
    {
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string']
        ];
    }

    /**
     * Intenta autenticar las credenciales de la petición.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        if (!\Illuminate\Support\Facades\Auth::attempt($this->only('email', 'password'))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }
    }
}
