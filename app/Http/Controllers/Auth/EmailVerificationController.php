<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de verificación de email.
 *
 * Implementa dos endpoints:
 *   1. verify($id, $hash) → Verifica el email del usuario
 *   2. notification() → Reenvía el email de verificación
 *
 * Usa las firmas (signed URLs) de Laravel para asegurar
 * que el enlace de verificación no sea falsificable.
 */
class EmailVerificationController extends Controller
{
    /**
     * Verifica el email del usuario.
     *
     * El enlace de verificación contiene:
     *  - {id}: UUID del usuario
     *  - {hash}: sha1 del email del usuario
     *
     * Laravel verifica que el hash coincida con el email actual
     * del usuario, y que la URL esté firmada (no haya sido
     * modificada por un atacante).
     *
     * @param  string  $id  UUID del usuario
     * @param  string  $hash  sha1 del email del usuario
     */
    public function verify(string $id, string $hash, Request $request): JsonResponse
    {
        // Buscar usuario por UUID
        $user = User::findOrFail($id);

        // La firma de la URL (expiración + integridad) ya fue validada por el
        // middleware 'signed' en la ruta. Aquí solo resta verificar que el
        // hash coincida con el email del usuario (integridad del destinatario).
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enlace de verificación inválido.',
            ], 403);
        }

        // Marcar email como verificado (si no lo estaba ya)
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'message' => 'El email ya estaba verificado.',
            ], 200);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verificado correctamente.',
        ], 200);
    }

    /**
     * Reenvía el email de verificación.
     *
     * Rate limited a 1 vez por minuto (throttle:10,1 en rutas).
     * Si el email ya está verificado, responde error.
     */
    public function notification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El email ya está verificado.',
            ], 400);
        }

        // Laravel envía el email usando el Notifiable trait
        $user->sendEmailVerificationNotification();

        return response()->json([
            'status' => 'success',
            'message' => 'Email de verificación reenviado.',
        ], 200);
    }
}
