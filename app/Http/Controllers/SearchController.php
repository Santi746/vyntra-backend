<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ClubResource;
use App\Http\Resources\UserResource;
use App\Http\Requests\Search\SearchRequest;

/**
 * Controlador de búsqueda global.
 *
 * Busca clubes y usuarios por texto con filtro de tipo.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\App\Http\Requests\Search\SearchRequest $request)
 */
class SearchController extends Controller
{
    /**
     * Busca clubes y/o usuarios según el filtro especificado.
     *
     * @param SearchRequest $request Query params: q (requerido), filter (opcional: all|clubs|users)
     * @return JsonResponse
     */
    public function index(SearchRequest $request): JsonResponse
    {
        $query = $request->validated('q');
        $filter = $request->validated('filter') ?? 'all';

        // Calculamos SIEMPRE ambos buckets, aunque el filtro pida solo uno.
        // Esto garantiza que el frontend siempre reciba las dos claves (`clubs` y `users`)
        // en la respuesta, evitando errores de "cannot read property of undefined" en el cliente.
        $clubs = in_array($filter, ['all', 'clubs'])
            ? Club::where('name', 'ilike', "%{$query}%")
                ->orWhere('description', 'ilike', "%{$query}%")
                ->with('clubOwner')
                ->cursorPaginate(10)
            : null;
        $users = in_array($filter, ['all', 'users'])
            ? User::where('username', 'ilike', "%{$query}%")
                ->orWhere('user_tag', 'ilike', "%{$query}%")
                ->cursorPaginate(10)
            : null;

        return response()->json([
            'status' => 'success',
            'data' => [
                'clubs' => $clubs ? $this->paginatedResponse($clubs, ClubResource::class) : null,
                'users' => $users ? $this->paginatedResponse($users, UserResource::class) : null,
            ],
        ]);
    }

    /**
     * Construye la estructura paginada estándar para un conjunto de resultados.
     *
     * @param mixed $paginator Resultado de cursorPaginate o null
     * @param string $resourceClass FQN del Resource a usar
     * @return array|null
     */
    private function paginatedResponse($paginator, string $resourceClass): ?array
    {
        if (!$paginator) {
            return null;
        }

        return [
            'data' => $resourceClass::collection($paginator->items()),
            'meta' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'per_page' => $paginator->perPage(),
            ],
        ];
    }
}
