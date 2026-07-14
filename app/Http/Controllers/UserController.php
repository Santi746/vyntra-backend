<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\CompleteProfileRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\SessionResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de perfil de usuario y sesiones.
 */
class UserController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new UserResource($request->user()->load('memberships')),
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => new UserResource($user->load('memberships')),
        ]);
    }

    public function updateProfile(UpdateUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $request->user()->update($validated);

        return response()->json([
            'status' => 'success',
            'data' => new UserResource($request->user()->fresh()),
        ]);
    }

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

    /**
     * Completa el perfil del usuario tras OAuth.
     *
     * Cuando un usuario se registra con Google/GitHub/Discord,
     * username/first_name/last_name quedan null. El frontend
     * redirige a /auth/complete-profile donde el user elige
     * su username y escribe nombre/apellido.
     *
     * Este endpoint persiste esos 3 campos y marca profile_completed=true
     * en futuras respuestas del AuthResource.
     */
    public function completeProfile(CompleteProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $request->user()->update($validated);
        } catch (UniqueConstraintViolationException $e) {
            // W5 (race condition): dos usuarios OAuth completando perfil
            // con el mismo username al mismo tiempo. La DB tiene unique
            // en 'username', así que el perdedor de la carrera falla aquí.
            // Respondemos 409 en lugar de 500.
            return response()->json([
                'status' => 'error',
                'message' => 'El nombre de usuario ya está en uso. Por favor elige otro.',
            ], 409);
        }

        return response()->json([
            'status' => 'success',
            'data' => new UserResource($request->user()->fresh()),
        ], 200);
    }
}
