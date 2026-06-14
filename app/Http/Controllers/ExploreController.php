<?php

namespace App\Http\Controllers;

use App\Http\Resources\ClubResource;
use App\Models\Club;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de exploración de clubes.
 */
class ExploreController extends Controller
{
    // TODO(dashboard-remodel): Devolver featured_clubs + categories en lugar de lista plana.
    public function index(Request $request): JsonResponse
    {
        $featured = Club::with('clubOwner')
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(15);

        return response()->json([
            'data' => ClubResource::collection($featured->items()),
            'meta' => [
                'next_cursor' => $featured->nextCursor()?->encode(),
                'per_page' => $featured->perPage(),
            ],
        ]);
    }
}
