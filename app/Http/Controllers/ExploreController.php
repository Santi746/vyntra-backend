<?php

namespace App\Http\Controllers;

use App\Models\Club;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Resources\ClubResource;

/**
 * Controlador de exploración de clubes.
 *
 * Lista clubes destacados/descubrimiento para usuarios no miembros.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request)
 */
class ExploreController extends Controller
{
    /**
     * Lista clubes públicos para explorar.
     *
     * TODO(dashboard-remodel): La respuesta actual es una lista paginada plana de clubes.
     * Cuando se remodele el dashboard, este endpoint deberá evolucionar para devolver una
     * estructura con `featured_clubs` (clubes curados/aleatorios destacados) y `categories`
     * (secciones de descubrimiento). Por ahora el frontend se adapta a la lista plana.
     *
     * @param Request $request
     * @return JsonResponse
     */
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
