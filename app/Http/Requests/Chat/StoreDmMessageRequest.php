<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

/**
 * StoreDmMessageRequest
 * 
 * Gestiona la validación para el envío de un nuevo mensaje directo (DM) entre usuarios.
 * 
 * @package App\Http\Requests\Chat
 * @method string method() HTTP POST
 */
class StoreDmMessageRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el futuro, aquí se validará si los usuarios son amigos o si la conversación es válida.
     * 
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la petición.
     * 
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:4000'],
            'client_uuid' => ['required', 'uuid'], // Obligatorio en creación para evitar duplicaciones
            'parent_message_uuid' => ['nullable', 'uuid', 'exists:dm_messages,uuid'],
        ];
    }
}
