<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Gestiona la validación para la actualización parcial del perfil y credenciales del usuario autenticado.
 *
 * @method string method() HTTP PATCH
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepara los datos antes de validar.
     *
     * Mapea new_password a password para que el modelo lo hashee automáticamente.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('new_password')) {
            $this->merge([
                'password' => $this->input('new_password'),
            ]);
        }
    }

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
            'password' => ['sometimes', 'string'],
        ];
    }

    /**
     * Validaciones después de las reglas estándar.
     *
     * Verifica que la contraseña actual sea correcta antes de permitir el cambio.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->has('current_password') && ! Hash::check($this->current_password, $this->user()->password)) {
                    $validator->errors()->add('current_password', 'La contraseña actual no es correcta.');
                }
            },
        ];
    }
}
