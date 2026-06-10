<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\NotificationResource;

/**
 * Controlador de notificaciones.
 *
 * Lista notificaciones del usuario autenticado y marca una como leída.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse markAsRead(\Illuminate\Http\Request $request, \App\Models\Notification $notification)
 */
class NotificationController extends Controller
{
    /**
     * Lista las notificaciones del usuario autenticado.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_uuid', $request->user()->uuid)
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(15);

        return response()->json([
            'data' => NotificationResource::collection($notifications->items()),
            'meta' => [
                'next_cursor' => $notifications->nextCursor()?->encode(),
                'per_page' => $notifications->perPage(),
            ],
        ]);
    }

    /**
     * Marca una notificación como leída.
     *
     * @param Request $request
     * @param Notification $notification Notificación a marcar
     * @return JsonResponse
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);

        $notification->update(['is_read' => true]);

        return response()->json([
            'status' => 'success',
            'data' => new NotificationResource($notification),
        ]);
    }
}
