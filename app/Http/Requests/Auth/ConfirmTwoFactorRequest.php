<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la confirmación de activación de 2FA.
 *
 * Requiere el código TOTP de 6 dígitos que el usuario
 * obtiene al escanear el QR generado por enable().
 */
class ConfirmTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
