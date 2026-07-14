<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la finalización del perfil tras OAuth.
 *
 * El user llega del callback de Google/GitHub/Discord con
 * username/first_name/last_name null. Este endpoint recibe los
 * 3 datos de oro y los persiste.
 */
class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza username a minúsculas antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => strtolower($this->username),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[^<>]+$/',
                // El user actual no cuenta para la validación unique
                // (por si reenvía el mismo username)
                Rule::unique('users', 'username')->ignore($this->user()->uuid, 'uuid'),
            ],
            'first_name' => ['required', 'string', 'max:50', 'regex:/^[^<>]+$/'],
            'last_name' => ['required', 'string', 'max:50', 'regex:/^[^<>]+$/'],
        ];
    }
}
