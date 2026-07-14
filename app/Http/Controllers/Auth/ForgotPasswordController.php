<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * Envía un email con el enlace de restablecimiento de contraseña.
 *
 * Por seguridad, SIEMPRE responde con success 200,
 * independientemente de si el email existe o no.
 * Esto evita que un atacante pueda enumerar emails registrados.
 */
class ForgotPasswordController extends Controller
{
    /**
     * __invoke permite usar este controlador como invocable
     * en las rutas (single-action controller).
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Laravel internamente:
        // 1. Busca el email en users
        // 2. Genera un token aleatorio
        // 3. Lo guarda en password_reset_tokens
        // 4. Envía un email con el token (usando el template de notificaciones)
        $status = Password::sendResetLink($validated);

        // Respondemos success siempre (seguridad por oscuridad)
        return response()->json([
            'status' => 'success',
            'message' => __('Si el correo existe, recibirás un enlace de restablecimiento.'),
        ], 200);
    }
}
