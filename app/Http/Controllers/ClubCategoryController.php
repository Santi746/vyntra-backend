<?php

namespace App\Http\Controllers;

use App\Http\Requests\Club\StoreClubCategoryRequest;
use App\Http\Requests\Club\UpdateClubCategoryRequest;
use App\Http\Resources\ClubCategoryResource;
use App\Models\Club;
use App\Models\ClubCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD de categorías que agrupan canales dentro de un club.
 * Filtra categorías privadas según permiso VIEW_CHANNELS.
 */
class ClubCategoryController extends Controller
{
    public function index(Club $club): JsonResponse
    {
        Gate::authorize('viewAny', [ClubCategory::class, $club]);

        $canSeePrivate = Gate::allows('viewPrivateChannels', $club);

        $categoriesQuery = ClubCategory::where('club_uuid', $club->uuid)
            ->with(['channels' => function ($q) use ($canSeePrivate) {
                if (! $canSeePrivate) {
                    $q->where('is_private', false);
                }
                $q->orderBy('sort_order');
            }])
            ->orderBy('sort_order', 'asc');

        if (! $canSeePrivate) {
            $categoriesQuery->where('is_private', false);
        }

        $categories = $categoriesQuery->cursorPaginate(15);

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
     * @param  StoreClubCategoryRequest  $request  Validación de la categoría
     * @return JsonResponse 201 si se creó, 200 si ya existía
     */
    public function store(StoreClubCategoryRequest $request, Club $club): JsonResponse
    {
        Gate::authorize('create', [ClubCategory::class, $club]);

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
     * @param  UpdateClubCategoryRequest  $request  Validación con category_uuid y campos a actualizar
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
     * @param  ClubCategory  $category  Categoría a eliminar
     */
    public function destroy(Club $club, ClubCategory $category): Response
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return response()->noContent();
    }
}
