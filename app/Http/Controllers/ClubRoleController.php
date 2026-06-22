<?php

namespace App\Http\Controllers;

use App\Events\Club\RoleCreated;
use App\Events\Club\RoleDeleted;
use App\Events\Club\RoleUpdated;
use App\Http\Requests\Club\StoreClubRoleRequest;
use App\Http\Requests\Club\UpdateClubRoleRequest;
use App\Http\Resources\ClubRoleResource;
use App\Models\Club;
use App\Models\ClubRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de roles de club.
 */
class ClubRoleController extends Controller
{
    public function index(Club $club): JsonResponse
    {
        Gate::authorize('viewAny', [ClubRole::class, $club]);

        $roles = ClubRole::where('club_uuid', $club->uuid)
            ->orderBy('sort_order')
            ->cursorPaginate(15);

        return response()->json([
            'data' => ClubRoleResource::collection($roles->items()),
            'meta' => [
                'next_cursor' => $roles->nextCursor()?->encode(),
                'per_page' => $roles->perPage(),
            ],
        ]);
    }

    // Crea un rol en el club (idempotente por client_uuid).
    public function store(StoreClubRoleRequest $request, Club $club): JsonResponse
    {
        Gate::authorize('create', [ClubRole::class, $club]);

        $validated = $request->validated();

        $role = ClubRole::firstOrCreate(
            [
                'club_uuid' => $club->uuid,
                'client_uuid' => $validated['client_uuid'],
            ],
            [
                'name' => $validated['name'],
                'color' => $validated['color'],
                'permissions' => $validated['permissions'] ?? 0,
                'sort_order' => (ClubRole::where('club_uuid', $club->uuid)->max('sort_order') ?? 0) + 1,
                'is_fixed' => false,
            ]
        );

        if ($role->wasRecentlyCreated) {
            RoleCreated::dispatch($role);
        }

        return response()->json([
            'status' => 'success',
            'data' => new ClubRoleResource($role),
        ], $role->wasRecentlyCreated ? 201 : 200);
    }

    // El UUID del rol se obtiene del body (`uuid`), no de la URL.
    public function update(UpdateClubRoleRequest $request, Club $club): JsonResponse
    {
        $validated = $request->validated();

        $role = ClubRole::where('club_uuid', $club->uuid)
            ->where('uuid', $validated['uuid'])
            ->firstOrFail();

        Gate::authorize('update', [ClubRole::class, $club, $role]);

        $role->update(Arr::except($validated, ['uuid', 'client_uuid']));

        RoleUpdated::dispatch($role);

        return response()->json([
            'status' => 'success',
            'data' => new ClubRoleResource($role),
        ]);
    }

    // Elimina un rol (soft delete).
    public function destroy(Club $club, ClubRole $role): Response
    {
        Gate::authorize('delete', [ClubRole::class, $club, $role]);

        $role->delete();

        RoleDeleted::dispatch((string) $role->uuid, (string) $club->uuid);

        return response()->noContent();
    }
}
