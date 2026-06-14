<?php

namespace App\Http\Controllers;

use App\Http\Requests\Club\StoreClubRequest;
use App\Http\Requests\Club\UpdateClubRequest;
use App\Http\Resources\ClubResource;
use App\Models\Club;
use App\Models\ClubMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD de clubes con auto-membresía para el creador.
 * Incluye endpoint preview público para usuarios no miembros.
 */
class ClubController extends Controller
{
    /**
     * Lista los clubes del usuario autenticado.
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
     * Solo miembros pueden acceder (Gate 'view'). Los canales y categorías
     * privados se filtran según Gate 'viewPrivateChannels' del ClubPolicy:
     * el owner y los ADMINISTRATOR ven todos, los demás necesitan VIEW_CHANNELS.
     */
    public function show(Club $club): JsonResponse
    {
        Gate::authorize('view', $club);

        $canSeePrivate = Gate::allows('viewPrivateChannels', $club);

        $categoriesQuery = $club->categories()->orderBy('sort_order');
        if (! $canSeePrivate) {
            $categoriesQuery->where('is_private', false);
        }

        $categories = $categoriesQuery->with(['channels' => function ($q) use ($canSeePrivate) {
            $q->orderBy('sort_order');
            if (! $canSeePrivate) {
                $q->where('is_private', false);
            }
        }])->get();

        $club->setRelation('categories', $categories);
        $club->load('clubOwner');

        return response()->json([
            'status' => 'success',
            'data' => new ClubResource($club),
        ]);
    }

    /**
     * Preview público de un club para usuarios no miembros.
     *
     * Excepción arquitectónica: este es el único endpoint de club que no
     * requiere membresía. Devuelve solo datos básicos: name, banner, avatar,
     * descripción, conteo de miembros, y un flag `is_member` calculado.
     *
     * Requiere autenticación (auth:sanctum) para poder calcular `is_member`,
     * pero NO requiere Gate.
     */
    public function preview(Club $club): JsonResponse
    {
        $user = request()->user();

        $isMember = $user
            ? $club->members()->where('user_uuid', $user->uuid)->exists()
            : false;

        $club->loadCount(['members as members_count']);
        $club->setAttribute('online_count', 0);

        return response()->json([
            'status' => 'success',
            'data' => array_merge(
                (new ClubResource($club))->toArray(request()),
                ['is_member' => $isMember],
            ),
        ]);
    }

    /**
     * Crea un nuevo club.
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
            ClubMember::firstOrCreate([
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
     */
    public function destroy(Club $club): Response
    {
        Gate::authorize('delete', $club);
        $club->delete();

        return response()->noContent();
    }
}
