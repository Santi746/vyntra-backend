<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Gestiona la validación para el envío de un nuevo mensaje directo (DM) entre usuarios.
 *
 * @method string method() HTTP POST
 */
class StoreDmMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
            'client_uuid' => ['required', 'uuid'], // Obligatorio en creación para evitar duplicaciones
            'parent_message_uuid' => ['nullable', 'uuid', 'exists:dm_messages,uuid'],
        ];
    }
}
