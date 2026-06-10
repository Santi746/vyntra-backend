<?php

namespace App\Http\Controllers;

use App\Models\DmConversation;
use App\Models\DmMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\DmMessageResource;
use App\Http\Requests\Chat\StoreDmMessageRequest;

/**
 * Controlador de mensajes de conversaciones DM.
 *
 * Lista mensajes de una conversación y crea nuevos con idempotencia.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\App\Models\DmConversation $dmConversation)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\Chat\StoreDmMessageRequest $request, \App\Models\DmConversation $dmConversation)
 */
class DmMessageController extends Controller
{
    /**
     * Lista los mensajes de una conversación DM.
     *
     * @param DmConversation $dmConversation Conversación de la cual listar mensajes
     * @return JsonResponse
     */
    public function index(DmConversation $dmConversation): JsonResponse
    {
        Gate::authorize('view', $dmConversation);

        $messages = DmMessage::where('dm_conversation_uuid', $dmConversation->uuid)
            ->with('sender')
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(20);

        return response()->json([
            'data' => DmMessageResource::collection($messages->items()),
            'meta' => [
                'next_cursor' => $messages->nextCursor()?->encode(),
                'per_page' => $messages->perPage(),
            ],
        ]);
    }

    /**
     * Envía un nuevo mensaje en una conversación DM (idempotente).
     *
     * @param StoreDmMessageRequest $request Validación del mensaje
     * @param DmConversation $dmConversation Conversación destino
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
    public function store(StoreDmMessageRequest $request, DmConversation $dmConversation): JsonResponse
    {
        Gate::authorize('create', $dmConversation);

        $validated = $request->validated();

        $message = DmMessage::firstOrCreate(
            [
                'dm_conversation_uuid' => $dmConversation->uuid,
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
            'data' => new DmMessageResource($message),
        ], $message->wasRecentlyCreated ? 201 : 200);
    }
}
