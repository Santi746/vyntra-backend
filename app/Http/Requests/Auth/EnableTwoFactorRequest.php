<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Valida la solicitud de activación de 2FA.
 *
 * Requiere la contraseña actual como medida de seguridad.
 * Usa after() para verificar la contraseña contra el hash en DB.
 */
class EnableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Verifica que la contraseña actual sea correcta.
     * Esto se ejecuta DESPUÉS de que las reglas pasen.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $user = $this->user();

                if (! $user || ! Hash::check($this->password, $user->password)) {
                    $validator->errors()->add(
                        'password',
                        'La contraseña actual es incorrecta.'
                    );
                }
            },
        ];
    }
}
