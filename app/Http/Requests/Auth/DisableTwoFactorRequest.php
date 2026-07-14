<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Valida la solicitud de desactivación de 2FA.
 *
 * Requiere DOBLE verificación:
 *  1. Contraseña actual (quién eres)
 *  2. Código TOTP (tienes acceso al autenticador)
 *
 * Ambas se verifican en after().
 */
class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ];
    }

    /**
     * Verifica password + código TOTP después de las reglas básicas.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $user = $this->user();

                // 1. Verificar contraseña
                if (! $user || ! Hash::check($this->password, $user->password)) {
                    $validator->errors()->add(
                        'password',
                        'La contraseña actual es incorrecta.'
                    );

                    return;
                }

                // 2. Verificar código TOTP (usar el método del modelo — DRY)
                if (! $user->verifyTwoFactorCode($this->code)) {
                    $validator->errors()->add(
                        'code',
                        'El código TOTP es inválido.'
                    );
                }
            },
        ];
    }
}
