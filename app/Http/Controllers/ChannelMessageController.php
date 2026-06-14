<?php

namespace App\Http\Controllers;

use App\Http\Requests\Chat\StoreChannelMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\ChannelMessage;
use App\Models\ClubChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de mensajes de canales de club.
 */
class ChannelMessageController extends Controller
{
    public function index(ClubChannel $channel): JsonResponse
    {
        Gate::authorize('viewAny', [ChannelMessage::class, $channel]);

        $messages = ChannelMessage::where('club_channel_uuid', $channel->uuid)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(20);

        return response()->json([
            'data' => MessageResource::collection($messages->items()),
            'meta' => [
                'next_cursor' => $messages->nextCursor()?->encode(),
                'per_page' => $messages->perPage(),
            ],
        ]);
    }

    // Crea un mensaje en un canal (idempotente por client_uuid).
    public function store(StoreChannelMessageRequest $request, ClubChannel $channel): JsonResponse
    {
        Gate::authorize('create', [ChannelMessage::class, $channel]);

        $validated = $request->validated();

        $message = ChannelMessage::firstOrCreate(
            [
                'club_channel_uuid' => $channel->uuid,
                'client_uuid' => $validated['client_uuid'],
            ],
            [
                'sender_uuid' => $request->user()->uuid,
                'content' => $validated['content'],
                'parent_message_uuid' => $validated['parent_message_uuid'] ?? null,
            ]
        );

        $message->load('sender');

        return response()->json([
            'status' => 'success',
            'data' => new MessageResource($message),
        ], $message->wasRecentlyCreated ? 201 : 200);
    }
}
