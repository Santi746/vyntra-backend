<?php

namespace App\Http\Controllers;

use App\Events\Dm\DmConversationCreated;
use App\Http\Requests\Chat\StoreDmConversationRequest;
use App\Http\Resources\DmConversationResource;
use App\Models\DmConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de conversaciones DM.
 */
class DmConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = DmConversation::where('user_one_uuid', $request->user()->uuid)
            ->orWhere('user_two_uuid', $request->user()->uuid)
            ->with(['userOne', 'userTwo', 'lastMessage'])
            ->withCount(['messages as unread_count' => function ($q) use ($request) {
                $q->where('sender_uuid', '!=', $request->user()->uuid);
            }])
            ->orderBy('updated_at', 'desc')
            ->cursorPaginate(15);

        return response()->json([
            'data' => DmConversationResource::collection($conversations->items()),
            'meta' => [
                'next_cursor' => $conversations->nextCursor()?->encode(),
                'per_page' => $conversations->perPage(),
            ],
        ]);
    }

    public function show(DmConversation $dmConversation, Request $request): JsonResponse
    {
        Gate::authorize('view', $dmConversation);

        $dmConversation->load(['userOne', 'userTwo', 'lastMessage']);
        $dmConversation->loadCount(['messages as unread_count' => function ($q) use ($request) {
            $q->where('sender_uuid', '!=', $request->user()->uuid);
        }]);

        return response()->json([
            'status' => 'success',
            'data' => new DmConversationResource($dmConversation),
        ]);
    }

    // Ordena los UUIDs alfabéticamente para cumplir la constraint única.
    public function store(StoreDmConversationRequest $request): JsonResponse
    {
        $user = $request->user()->uuid;
        $recipient = $request->validated()['recipient_uuid'];

        $conversation = DmConversation::firstOrCreate(
            [
                'user_one_uuid' => strcmp($user, $recipient) < 0 ? $user : $recipient,
                'user_two_uuid' => strcmp($user, $recipient) < 0 ? $recipient : $user,
            ]
        );

        $conversation->load(['userOne', 'userTwo']);

        if ($conversation->wasRecentlyCreated) {
            DmConversationCreated::dispatch(
                (string) $conversation->uuid,
                (string) $user,
                (string) $recipient
            );
        }

        return response()->json([
            'status' => 'success',
            'data' => new DmConversationResource($conversation),
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }
}
