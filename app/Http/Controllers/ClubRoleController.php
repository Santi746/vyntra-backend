<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\ClubRoleResource;
use App\Http\Requests\Club\StoreClubRoleRequest;
use App\Http\Requests\Club\UpdateClubRoleRequest;

/**
 * Controlador de roles de club.
 *
 * CRUD de roles personalizables dentro de un club.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\Club\StoreClubRoleRequest $request, \App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse update(\App\Http\Requests\Club\UpdateClubRoleRequest $request, \App\Models\Club $club)
 * @method \Illuminate\Http\Response destroy(\App\Models\Club $club, \App\Models\ClubRole $role)
 */
class ClubRoleController extends Controller
{
    /**
     * Lista los roles de un club.
     *
     * @param Club $club
     * @return JsonResponse
     */
    public function index(Club $club): JsonResponse
    {
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

    /**
     * Crea un rol en el club (idempotente).
     *
     * @param StoreClubRoleRequest $request Validación del rol
     * @param Club $club
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
    public function store(StoreClubRoleRequest $request, Club $club): JsonResponse
    {
        $validated = $request->validated();

        $role = ClubRole::firstOrCreate(
            [
                'club_uuid' => $club->uuid,
                'client_uuid' => $validated['client_uuid']
            ],
            [
                'name' => $validated['name'],
                'color' => $validated['color'],
                'permissions' => $validated['permissions'] ?? 0,
                'sort_order' => (ClubRole::where('club_uuid', $club->uuid)->max('sort_order') ?? 0) + 1,
                'is_fixed' => false,
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => new ClubRoleResource($role),
        ], $role->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Actualiza los datos de un rol.
     *
     * El UUID del rol se obtiene del body (campo `uuid`), no de la URL.
     *
     * @param UpdateClubRoleRequest $request Validación con uuid, client_uuid y campos a actualizar
     * @param Club $club
     * @return JsonResponse
     */
    public function update(UpdateClubRoleRequest $request, Club $club): JsonResponse
    {
        Gate::authorize('update', $club);

        $validated = $request->validated();

        $role = ClubRole::where('club_uuid', $club->uuid)
            ->where('uuid', $validated['uuid'])
            ->firstOrFail();

        $role->update(Arr::except($validated, ['uuid', 'client_uuid']));

        return response()->json([
            'status' => 'success',
            'data' => new ClubRoleResource($role),
        ]);
    }

    /**
     * Elimina un rol (soft delete).
     *
     * @param Club $club
     * @param ClubRole $role Rol a eliminar
     * @return Response
     */
    public function destroy(Club $club, ClubRole $role): Response
    {
        Gate::authorize('update', $club);
        $role->delete();

        return response()->noContent();
    }
}
