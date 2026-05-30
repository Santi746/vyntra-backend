<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * RespondFriendshipRequest
 * 
 * Gestiona la validación para responder (aceptar o rechazar) una solicitud de amistad existente.
 * 
 * @package App\Http\Requests\User
 * @method string method() HTTP PATCH
 */
class RespondFriendshipRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     * En el controlador se verificará que el usuario autenticado sea el destinatario real de la solicitud.
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
            'client_uuid' => ['sometimes', 'uuid'], // Opcional porque la actualización de estado (PATCH) sobre un UUID de solicitud es intrínsecamente idempotente.
            'action' => ['required', 'string', 'in:accept,decline'], // Sincronizado con el Frontend (Next.js) que envía "decline" para rechazar.
        ];
    }
}
