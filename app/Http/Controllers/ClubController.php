<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\ClubResource;
use App\Http\Requests\Club\StoreClubRequest;
use App\Http\Requests\Club\UpdateClubRequest;

/**
 * Controlador de gestión de clubes.
 *
 * CRUD completo de clubes con auto-membresía para el creador.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse show(\App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\Club\StoreClubRequest $request)
 * @method \Illuminate\Http\JsonResponse update(\App\Http\Requests\Club\UpdateClubRequest $request, \App\Models\Club $club)
 * @method \Illuminate\Http\Response destroy(\App\Models\Club $club)
 */
class ClubController extends Controller
{
    /**
     * Lista los clubes del usuario autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $memberships = $request->user()->memberships()
            ->with('club.clubOwner')
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(15);

        $clubs = $memberships->map->club;

        return response()->json([
            'data' => ClubResource::collection($clubs),
            'meta' => [
                'next_cursor' => $memberships->nextCursor()?->encode(),
                'per_page' => $memberships->perPage(),
            ],
        ]);
    }

    /**
     * Muestra los detalles de un club específico.
     *
     * @param Club $club
     * @return JsonResponse
     */
    public function show(Club $club): JsonResponse
    {
        Gate::authorize('view', $club);

        $club->load([
            'clubOwner',
            'categories' => fn ($q) => $q->orderBy('sort_order'),
            'categories.channels' => fn ($q) => $q->orderBy('sort_order'),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => new ClubResource($club),
        ]);
    }

    /**
     * Crea un nuevo club.
     *
     * @param StoreClubRequest $request
     * @return JsonResponse
     */
    public function store(StoreClubRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $club = Club::firstOrCreate(
            ['client_uuid' => $validated['client_uuid']],
            [
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'category_tag' => $validated['category_tag'],
                'owner_uuid' => $request->user()->uuid,
                'avatar_url' => $validated['avatar_url'] ?? null,
                'banner_url' => $validated['banner_url'] ?? null,
            ]
        );

        $club->load('clubOwner');

        if ($club->wasRecentlyCreated) {
            ClubMember::create([
                'user_uuid' => $request->user()->uuid,
                'club_uuid' => $club->uuid,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => new ClubResource($club),
        ], $club->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Actualiza los datos de un club existente.
     *
     * @param UpdateClubRequest $request
     * @param Club $club
     * @return JsonResponse
     */
    public function update(UpdateClubRequest $request, Club $club): JsonResponse
    {
        Gate::authorize('update', $club);

        $validated = $request->validated();
        $club->update($validated);
        $club->load('clubOwner');

        return response()->json([
            'status' => 'success',
            'data' => new ClubResource($club),
        ]);
    }

    /**
     * Elimina un club (soft delete).
     *
     * @param Club $club
     * @return Response
     */
    public function destroy(Club $club): Response
    {
        Gate::authorize('delete', $club);
        $club->delete();

        return response()->noContent();
    }
}
