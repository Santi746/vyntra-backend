<?php

namespace App\Http\Controllers;

use App\Http\Requests\Club\StoreClubChannelRequest;
use App\Http\Requests\Club\UpdateClubChannelRequest;
use App\Http\Resources\ClubChannelResource;
use App\Models\Club;
use App\Models\ClubChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD de canales dentro de las categorías de un club.
 */
class ClubChannelController extends Controller
{
    public function index(Club $club): JsonResponse
    {
        Gate::authorize('viewAny', [ClubChannel::class, $club]);

        $canSeePrivate = Gate::allows('viewPrivateChannels', $club);

        $channelsQuery = ClubChannel::whereHas('category', fn ($q) => $q->where('club_uuid', $club->uuid))
            ->orderBy('sort_order');

        if (! $canSeePrivate) {
            $channelsQuery->where('is_private', false);
        }

        $channels = $channelsQuery->cursorPaginate(15);

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
     * @param  StoreClubChannelRequest  $request  Validación del canal
     * @param  Club  $club  Club al que pertenece el canal
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
    public function store(StoreClubChannelRequest $request, Club $club): JsonResponse
    {
        Gate::authorize('create', [ClubChannel::class, $club]);

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
     * @param  ClubChannel  $channel  Canal a actualizar
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
     * @param  ClubChannel  $channel  Canal a eliminar
     */
    public function destroy(Club $club, ClubChannel $channel): Response
    {
        Gate::authorize('delete', $channel);

        $channel->delete();

        return response()->noContent();
    }
}
