<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\RespondFriendshipRequest;
use App\Http\Requests\User\StoreFriendshipRequest;
use App\Http\Resources\FriendshipResource;
use App\Models\Friendship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de amistades.
 */
class FriendshipController extends Controller
{
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

    // Envía solicitud de amistad (idempotente por client_uuid).
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

    // Acepta o rechaza una solicitud de amistad según `action` (accept/decline).
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
