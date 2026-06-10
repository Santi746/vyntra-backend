<?php

namespace App\Http\Controllers;

use App\Models\DmConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\DmConversationResource;
use App\Http\Requests\Chat\StoreDmConversationRequest;

/**
 * Controlador de conversaciones DM.
 *
 * Lista, muestra y crea conversaciones uno-a-uno entre usuarios.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse show(\App\Models\DmConversation $dmConversation, \Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\Chat\StoreDmConversationRequest $request)
 */
class DmConversationController extends Controller
{
    /**
     * Lista las conversaciones del usuario autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
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

    /**
     * Muestra una conversación DM si el usuario pertenece a ella.
     *
     * @param DmConversation $dmConversation Conversación a mostrar
     * @param Request $request
     * @return JsonResponse
     */
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

    /**
     * Crea una conversación DM (idempotente).
     *
     * Ordena los UUIDs alfabéticamente para cumplir con la
     * constraint única de la migración.
     *
     * @param StoreDmConversationRequest $request Validación con recipient_uuid
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
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

        return response()->json([
            'status' => 'success',
            'data' => new DmConversationResource($conversation),
        ], $conversation->wasRecentlyCreated ? 201 : 200);
    }
}
