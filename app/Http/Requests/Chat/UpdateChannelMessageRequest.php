<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para la edición de un mensaje dentro de un canal de club.
 *
 * @method string method() HTTP PATCH
 */
class UpdateChannelMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
        ];
    }
}
