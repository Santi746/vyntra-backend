<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\FriendshipResource;
use App\Http\Requests\User\StoreFriendshipRequest;
use App\Http\Requests\User\RespondFriendshipRequest;

/**
 * Controlador de amistades.
 *
 * Lista amistades aceptadas, solicitudes pendientes,
 * envía nuevas solicitudes (idempotente) y responde a ellas.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse pending(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\User\StoreFriendshipRequest $request)
 * @method \Illuminate\Http\JsonResponse respond(\App\Http\Requests\User\RespondFriendshipRequest $request, string $requestUuid)
 */
class FriendshipController extends Controller
{
    /**
     * Lista las amistades aceptadas del usuario autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $friendships = Friendship::where(function ($q) use ($request) {
            $q->where('sender_uuid', $request->user()->uuid)
              ->orWhere('receiver_uuid', $request->user()->uuid);
        })
            ->where('status', 'accepted')
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(15);

        return response()->json([
            'data' => FriendshipResource::collection($friendships->items()),
            'meta' => [
                'next_cursor' => $friendships->nextCursor()?->encode(),
                'per_page' => $friendships->perPage(),
            ],
        ]);
    }

    /**
     * Lista las solicitudes de amistad pendientes del usuario.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function pending(Request $request): JsonResponse
    {
        $requests = Friendship::where('receiver_uuid', $request->user()->uuid)
            ->where('status', 'pending')
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(15);

        return response()->json([
            'data' => FriendshipResource::collection($requests->items()),
            'meta' => [
                'next_cursor' => $requests->nextCursor()?->encode(),
                'per_page' => $requests->perPage(),
            ],
        ]);
    }

    /**
     * Envía una solicitud de amistad (idempotente).
     *
     * @param StoreFriendshipRequest $request Validación con receiver_uuid y client_uuid
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
    public function store(StoreFriendshipRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $friendship = Friendship::firstOrCreate(
            [
                'sender_uuid' => $request->user()->uuid,
                'client_uuid' => $validated['client_uuid'],
            ],
            [
                'receiver_uuid' => $validated['receiver_uuid'],
                'status' => 'pending',
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => new FriendshipResource($friendship),
        ], $friendship->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Responde a una solicitud de amistad (aceptar/rechazar).
     *
     * @param RespondFriendshipRequest $request Validación con action (accept/decline)
     * @param string $requestUuid UUID de la solicitud
     * @return JsonResponse
     */
    public function respond(RespondFriendshipRequest $request, string $requestUuid): JsonResponse
    {
        $friendship = Friendship::where('uuid', $requestUuid)
            ->where('receiver_uuid', $request->user()->uuid)
            ->where('status', 'pending')
            ->firstOrFail()
            ->load(['sender', 'receiver']);

        $validated = $request->validated();

        $friendship->update(['status' => $validated['action'] === 'accept' ? 'accepted' : 'declined']);

        return response()->json([
            'status' => 'success',
            'data' => new FriendshipResource($friendship),
        ]);
    }
}
