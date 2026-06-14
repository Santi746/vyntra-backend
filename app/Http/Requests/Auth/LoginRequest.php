<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Gestiona la validación para el inicio de sesión de usuarios en la plataforma.
 *
 * @method string method() HTTP POST
 */
class LoginRequest extends FormRequest
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
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Intenta autenticar las credenciales de la petición.
     *
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        if (! Auth::attempt($this->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }
    }
}
