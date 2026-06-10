<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Http\Resources\SessionResource;
use App\Http\Requests\User\UpdateUserRequest;

/**
 * Controlador de perfil de usuario y sesiones.
 *
 * Gestiona la visualización del perfil propio y de otros usuarios,
 * la actualización de datos del perfil y la lista de sesiones activas.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse me(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse show(\App\Models\User $user)
 * @method \Illuminate\Http\JsonResponse updateProfile(\App\Http\Requests\User\UpdateUserRequest $request)
 * @method \Illuminate\Http\JsonResponse sessions(\Illuminate\Http\Request $request)
 */
class UserController extends Controller
{
    /**
     * Obtiene los datos del usuario autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new UserResource($request->user()->load('memberships')),
        ]);
    }

    /**
     * Obtiene los datos de un usuario por su UUID.
     *
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new UserResource($user->load('memberships')),
        ]);
    }

    /**
     * Actualiza el perfil del usuario autenticado.
     *
     * @param UpdateUserRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $request->user()->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => new UserResource($request->user()->fresh()),
        ]);
    }

    /**
     * Lista las sesiones activas del usuario autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sessions(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => SessionResource::collection($tokens),
        ]);
    }
}
