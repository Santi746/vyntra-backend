<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\ClubChannelResource;
use App\Http\Requests\Club\StoreClubChannelRequest;
use App\Http\Requests\Club\UpdateClubChannelRequest;

/**
 * Controlador de canales de club.
 *
 * CRUD de canales dentro de las categorías de un club.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\Club\StoreClubChannelRequest $request, \App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse update(\App\Http\Requests\Club\UpdateClubChannelRequest $request, \App\Models\Club $club, \App\Models\ClubChannel $channel)
 * @method \Illuminate\Http\Response destroy(\App\Models\Club $club, \App\Models\ClubChannel $channel)
 */
class ClubChannelController extends Controller
{
    /**
     * Lista los canales de un club.
     *
     * @param Club $club
     * @return JsonResponse
     */
    public function index(Club $club): JsonResponse
    {
        $channels = ClubChannel::whereHas('category', fn($q) => $q->where('club_uuid', $club->uuid))
            ->orderBy('sort_order')
            ->cursorPaginate(15);

        return response()->json([
            'data' => ClubChannelResource::collection($channels->items()),
            'meta' => [
                'next_cursor' => $channels->nextCursor()?->encode(),
                'per_page' => $channels->perPage(),
            ],
        ]);
    }

    /**
     * Crea un canal en un club (idempotente).
     *
     * @param StoreClubChannelRequest $request Validación del canal
     * @param Club $club Club al que pertenece el canal
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
    public function store(StoreClubChannelRequest $request, Club $club): JsonResponse
    {
        $validated = $request->validated();

        $maxSortOrder = ClubChannel::where('category_uuid', $validated['category_uuid'])->max('sort_order') ?? 0;

        $channel = ClubChannel::firstOrCreate(
            ['category_uuid' => $validated['category_uuid'], 'client_uuid' => $validated['client_uuid']],
            [
                'name' => $validated['name'],
                'type' => $validated['type'],
                'is_private' => $validated['is_private'] ?? false,
                'sort_order' => $maxSortOrder + 1,
            ],
        );

        return response()->json(
            ['status' => 'success', 'data' => new ClubChannelResource($channel)],
            $channel->wasRecentlyCreated ? 201 : 200,
        );
    }

    /**
     * Actualiza los datos de un canal.
     *
     * @param UpdateClubChannelRequest $request
     * @param Club $club
     * @param ClubChannel $channel Canal a actualizar
     * @return JsonResponse
     */
    public function update(UpdateClubChannelRequest $request, Club $club, ClubChannel $channel): JsonResponse
    {
        Gate::authorize('update', $channel);

        $validated = $request->validated();
        $channel->update($validated);

        return response()->json(['status' => 'success', 'data' => new ClubChannelResource($channel)]);
    }

    /**
     * Elimina un canal (soft delete).
     *
     * @param Club $club
     * @param ClubChannel $channel Canal a eliminar
     * @return Response
     */
    public function destroy(Club $club, ClubChannel $channel): Response
    {
        Gate::authorize('delete', $channel);

        $channel->delete();

        return response()->noContent();
    }
}
