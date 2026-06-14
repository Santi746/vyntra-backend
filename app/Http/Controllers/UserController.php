<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UpdateUserRequest;
use App\Http\Resources\SessionResource;
use App\Http\Resources\UserResource;
use App\Models\User;
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
}
