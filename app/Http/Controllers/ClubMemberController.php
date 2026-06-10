<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Club;
use App\Models\ClubMember;
use App\Http\Resources\ClubMemberResource;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de miembros de clubs.
 *
 * El controller es SOLO un coordinador. La lógica de negocio
 * (duplicados, soft-deletes, etc.) va en otra capa o se delega
 * a la BD (constraints UNIQUE).
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request, \App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse store(\Illuminate\Http\Request $request, \App\Models\Club $club)
 * @method \Illuminate\Http\JsonResponse destroy(\Illuminate\Http\Request $request, \App\Models\Club $club, string $member)
 */
class ClubMemberController extends Controller
{
    /**
     * Lista los miembros de un club paginados por cursor.
     *
     * @param Request $request
     * @param Club $club
     * @return JsonResponse
     */
    public function index(Request $request, Club $club): JsonResponse
    {
        $members = ClubMember::where('club_uuid', $club->uuid)
            ->with(['user', 'roles'])
            ->orderBy('joined_at', 'asc')
            ->cursorPaginate(15);

        return response()->json([
            'data' => ClubMemberResource::collection($members->items()),
            'meta' => [
                'club_uuid' => $club->uuid,
                'next_cursor' => $members->nextCursor()?->encode(),
                'per_page' => $members->perPage(),
            ],
        ]);
    }

    /**
     * Une al usuario autenticado al club.
     *
     * Idempotente: si ya es miembro, devuelve la membresía existente.
     *
     * @param Request $request
     * @param Club $club
     * @return JsonResponse
     */
    public function store(Request $request, Club $club): JsonResponse
    {
        $membership = ClubMember::firstOrCreate(
            [
                'user_uuid' => $request->user()->uuid,
                'club_uuid' => $club->uuid,
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => new ClubMemberResource($membership->load('user')),
        ], $membership->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Elimina la membresía de un usuario en el club (soft delete).
     *
     * @param Request $request
     * @param Club $club
     * @param string $member UUID de la membresía
     * @return JsonResponse
     */
    public function destroy(Request $request, Club $club, string $member): Response
    {
        Gate::authorize('delete', [ClubMember::class, $club]);

        $membership = ClubMember::where('club_uuid', $club->uuid)
            ->where('uuid', $member)
            ->firstOrFail();

        $membership->delete();

        return response()->noContent();
    }
}
