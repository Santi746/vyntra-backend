<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el código 2FA durante el login.
 *
 * Acepta:
 *  - Código TOTP: exactamente 6 dígitos numéricos
 *  - Backup code: exactamente 8 caracteres alfanuméricos
 *
 * La validación lógica (si el código es correcto) se hace en el controller.
 */
class VerifyTwoFactorRequest extends FormRequest
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
