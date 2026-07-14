<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Restablece la contraseña del usuario usando el token
 * recibido por email y AUTENTICA al usuario automáticamente.
 *
 * Esto significa que el usuario no necesita hacer login después
 * de resetear su contraseña — ya recibe un token Sanctum.
 */
class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Password::reset() verifica:
        // 1. Que el token existe y no ha expirado
        // 2. Que el email coincide
        // 3. Ejecuta el callback con los datos validados
        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        // Si el token es inválido o expiró.
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'error',
                'message' => __($status), // Laravel traduce: "passwords.token", "passwords.expired", etc.
            ], 400);
        }

        // Autenticar al usuario automáticamente
        // Buscamos al usuario por email (ya validado por Password::reset)
        $user = User::where('email', $validated['email'])->firstOrFail(); // Busca la coincidencia. si no hay falla todo
        $token = $user->createToken('auth_token', ['*'])->plainTextToken; // Crea el token Auth_token con la habilidad TODOS (*)

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 200);
    }
}
