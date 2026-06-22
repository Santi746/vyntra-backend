<?php

namespace App\Http\Controllers;

use App\Events\User\NotificationRead;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de notificaciones.
 */
class NotificationController extends Controller
{
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

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        Gate::authorize('update', $notification);

        $notification->update(['is_read' => true]);

        NotificationRead::dispatch($notification);

        return response()->json([
            'status' => 'success',
            'data' => new NotificationResource($notification),
        ]);
    }
}
