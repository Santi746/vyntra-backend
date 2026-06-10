<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use App\Http\Resources\ClubCategoryResource;
use App\Http\Requests\Club\StoreClubCategoryRequest;
use App\Http\Requests\Club\UpdateClubCategoryRequest;

/**
 * Controlador de categorías de club.
 *
 * CRUD de categorías que agrupan canales dentro de un club.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\Club\StoreClubCategoryRequest $request, \App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse update(\App\Http\Requests\Club\UpdateClubCategoryRequest $request, \App\Models\Club $club)
 * @method \Illuminate\Http\Response destroy(\App\Models\Club $club, \App\Models\ClubCategory $category)
 */
class ClubCategoryController extends Controller
{
    /**
     * Lista las categorías de un club con sus canales.
     *
     * @param Club $club
     * @return JsonResponse
     */
    public function index(Club $club): JsonResponse
    {
        $categories = ClubCategory::where('club_uuid', $club->uuid)
            ->with('channels')
            ->orderBy('sort_order', 'asc')
            ->cursorPaginate(15);

        return response()->json([
            'data' => ClubCategoryResource::collection($categories->items()),
            'meta' => [
                'next_cursor' => $categories->nextCursor()?->encode(),
                'per_page' => $categories->perPage(),
            ],
        ]);
    }

    /**
     * Crea una categoría en el club (idempotente).
     *
     * @param StoreClubCategoryRequest $request Validación de la categoría
     * @param Club $club
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
    public function store(StoreClubCategoryRequest $request, Club $club): JsonResponse
    {
        $validated = $request->validated();

        $category = ClubCategory::firstOrCreate(
            ['club_uuid' => $club->uuid, 'client_uuid' => $validated['client_uuid']],
            [
                'name' => $validated['name'],
                'sort_order' => $validated['sort_order'] ?? (ClubCategory::where('club_uuid', $club->uuid)->max('sort_order') ?? 0) + 1,
                'is_private' => $validated['is_private'] ?? false,
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => new ClubCategoryResource($category),
        ], $category->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Actualiza los datos de una categoría.
     *
     * El UUID se obtiene del body (campo `category_uuid`), no de la URL.
     *
     * @param UpdateClubCategoryRequest $request Validación con category_uuid y campos a actualizar
     * @param Club $club
     * @return JsonResponse
     */
    public function update(UpdateClubCategoryRequest $request, Club $club): JsonResponse
    {
        $validated = $request->validated();

        $category = ClubCategory::where('club_uuid', $club->uuid)
            ->where('uuid', $validated['category_uuid'])
            ->firstOrFail();

        Gate::authorize('update', $category);

        $category->update(Arr::except($validated, ['category_uuid', 'client_uuid']));

        return response()->json([
            'status' => 'success',
            'data' => new ClubCategoryResource($category),
        ]);
    }

    /**
     * Elimina una categoría (soft delete).
     *
     * @param Club $club
     * @param ClubCategory $category Categoría a eliminar
     * @return Response
     */
    public function destroy(Club $club, ClubCategory $category): Response
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }
}
