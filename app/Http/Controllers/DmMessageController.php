<?php

namespace App\Http\Controllers;

use App\Events\Dm\DmMessageCreated;
use App\Events\Dm\DmMessageDeleted;
use App\Events\Dm\DmMessageUpdated;
use App\Http\Requests\Chat\StoreDmMessageRequest;
use App\Http\Requests\Chat\UpdateDmMessageRequest;
use App\Http\Resources\DmMessageResource;
use App\Models\DmConversation;
use App\Models\DmMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de mensajes de conversaciones DM.
 */
class DmMessageController extends Controller
{
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

    // Envía un mensaje DM (idempotente por client_uuid).
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

        if ($message->wasRecentlyCreated) {
            DmMessageCreated::dispatch($message);
        }

        return response()->json([
            'status' => 'success',
            'data' => new DmMessageResource($message),
        ], $message->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateDmMessageRequest $request, DmConversation $dmConversation, DmMessage $message): JsonResponse
    {
        Gate::authorize('update', $message);

        $validated = $request->validated();
        $message->update($validated);

        DmMessageUpdated::dispatch($message);

        return response()->json([
            'status' => 'success',
            'data' => new DmMessageResource($message),
        ]);
    }

    public function destroy(DmConversation $dmConversation, DmMessage $message): Response
    {
        Gate::authorize('delete', $message);

        $messageUuid = (string) $message->uuid;
        $conversationUuid = (string) $message->dm_conversation_uuid;
        $message->delete();

        DmMessageDeleted::dispatch($messageUuid, $conversationUuid);

        return response()->noContent();
    }
}
