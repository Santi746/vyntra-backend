<?php

namespace App\Http\Controllers;

use App\Models\ClubChannel;
use App\Models\ChannelMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\MessageResource;
use App\Http\Requests\Chat\StoreChannelMessageRequest;

/**
 * Controlador de mensajes de canales de club.
 *
 * Lista mensajes con paginación por cursor y crea mensajes
 * con idempotencia vía firstOrCreate usando client_uuid.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\App\Models\ClubChannel $channel)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\Chat\StoreChannelMessageRequest $request, \App\Models\ClubChannel $channel)
 */
class ChannelMessageController extends Controller
{
    /**
     * Lista los mensajes de un canal con paginación por cursor.
     *
     * @param ClubChannel $channel Canal del cual listar mensajes
     * @return JsonResponse
     */
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

    /**
     * Crea un nuevo mensaje en un canal (idempotente).
     *
     * @param StoreChannelMessageRequest $request Validación del mensaje
     * @param ClubChannel $channel Canal destino
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
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
