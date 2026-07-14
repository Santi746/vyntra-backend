<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource unificado para TODAS las respuestas de autenticación.
 *
 * Contiene:
 *  - user:       El usuario autenticado (UserResource)
 *  - token:      El token Sanctum en texto plano
 *  - token_type: Siempre 'Bearer'
 *  - requires_2fa: (opcional) true si el usuario debe completar 2FA
 *
 * @property-read User $resource
 */
class AuthResource extends JsonResource
{
    private string $token;

    private bool $requires2fa;

    /**
     * @param  User  $resource  El usuario autenticado
     * @param  string  $token  Token Sanctum en texto plano
     * @param  bool  $requires2fa  Si el token requiere verificación 2FA
     */
    public function __construct($resource, string $token, bool $requires2fa = false)
    {
        parent::__construct($resource);
        $this->token = $token;
        $this->requires2fa = $requires2fa;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'user' => new UserResource($this->resource),
            'token' => $this->token,
            'token_type' => 'Bearer',
            // El frontend redirige a /auth/complete-profile si esto es false
            'profile_completed' => $this->resource->username !== null,
        ];

        // Solo incluimos 'requires_2fa' si es true (YAGNI)
        if ($this->requires2fa) {
            $data['requires_2fa'] = true;
        }

        return $data;
    }
}
