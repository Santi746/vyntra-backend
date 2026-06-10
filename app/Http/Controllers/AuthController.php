<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\StoreUserRequest;

/**
 * Controlador de autenticación de Vyntra.
 *
 * Todas las respuestas exitosas siguen el Standard Envelope:
 * { "status": "success", "data": { ... } }
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse register(\App\Http\Requests\Auth\StoreUserRequest $request)
 * @method \Illuminate\Http\JsonResponse login(\App\Http\Requests\Auth\LoginRequest $request)
 * @method \Illuminate\Http\JsonResponse logout(\Illuminate\Http\Request $request)
 */
class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario en la base de datos.
     *
     * @param StoreUserRequest $request
     * @return JsonResponse
     */
    public function register(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'username'   => $validated['username'],
            'user_tag'   => $validated['user_tag'],
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Inicia sesión con credenciales existentes.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();

        /** @var \App\Models\User $user */
        $user = $request->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ], 200);
    }

    /**
     * Cierra la sesión del usuario actual.
     *
     * @param Request $request
     * @return JsonResponse
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
