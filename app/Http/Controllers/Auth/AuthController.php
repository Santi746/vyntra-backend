<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de autenticación de Vyntra.
 *
 * Maneja register, login (con/sin 2FA) y logout.
 */
class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario.
     *
     * 1. StoreUserRequest valida los datos
     * 2. Se crea el usuario (el cast 'hashed' del modelo hashea el password)
     * 3. Se emite un token Sanctum con ability ['*'] (completo)
     * 4. Se responde con AuthResource + status 201
     */
    public function register(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'username' => $validated['username'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Token completo — el usuario registrado no tiene 2FA
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 201);
    }

    /**
     * Inicia sesión con email y contraseña.
     *
     * 1. LoginRequest valida email + password (formato)
     * 2. Controller autentica con Auth::attempt()
     * 3. Si el usuario tiene 2FA:
     *      - Emite token con ability ['2fa_pending']
     *      - Responde con requires_2fa: true
     * 4. Si NO tiene 2FA:
     *      - Emite token con ability ['*']
     *      - Responde normal
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Autenticar credenciales de forma explícita y stateless contra el guard 'web'.
        // NO usamos Auth::attempt(): depende del guard por defecto y el guard sanctum
        // (RequestGuard) no expone attempt(), lo que 500earía el login si el default
        // fuese sanctum o si un test contamina el guard por defecto con actingAs().
        if (! Auth::guard('web')->validate($validated)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        /** @var User $user */
        $user = User::where('email', $validated['email'])->firstOrFail();

        // ─── Detección de 2FA ─────────────────────────────────
        if ($user->has2faEnabled()) {
            // Token limitado — solo puede acceder a /auth/2fa/verify
            $pendingToken = $user->createToken('2fa_pending', ['2fa_pending'])->plainTextToken;

            return response()->json([
                'status' => 'success',
                'data' => new AuthResource($user, $pendingToken, requires2fa: true),
            ], 200);
        }

        // Token completo — el usuario no tiene 2FA
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 200);
    }

    /**
     * Cierra la sesión del usuario.
     *
     * Revoca el token actual (lo borra de personal_access_tokens).
     * El frontend debe eliminar el token de su store después
     * de recibir la respuesta exitosa.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'data' => null,
        ], 200);
    }
}
