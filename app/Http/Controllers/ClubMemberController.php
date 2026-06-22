<?php

namespace App\Http\Controllers;

use App\Events\Club\MemberJoined;
use App\Events\Club\MemberLeft;
use App\Http\Resources\ClubMemberResource;
use App\Models\Club;
use App\Models\ClubMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de miembros de clubs.
 */
class ClubMemberController extends Controller
{
    public function index(Request $request, Club $club): JsonResponse
    {
        Gate::authorize('viewAny', [ClubMember::class, $club]);

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

    // Une al usuario autenticado al club (idempotente).
    public function store(Request $request, Club $club): JsonResponse
    {
        $membership = ClubMember::firstOrCreate(
            [
                'user_uuid' => $request->user()->uuid,
                'club_uuid' => $club->uuid,
            ]
        );

        if ($membership->wasRecentlyCreated) {
            MemberJoined::dispatch($club->uuid, $request->user()->uuid);
        }

        return response()->json([
            'status' => 'success',
            'data' => new ClubMemberResource($membership->load('user')),
        ], $membership->wasRecentlyCreated ? 201 : 200);
    }

    // Elimina la membresía de un usuario en el club (soft delete).
    public function destroy(Request $request, Club $club, string $member): Response
    {
        Gate::authorize('delete', [ClubMember::class, $club]);

        $membership = ClubMember::where('club_uuid', $club->uuid)
            ->where('uuid', $member)
            ->firstOrFail();

        $userUuid = (string) $membership->user_uuid;
        $membership->delete();

        MemberLeft::dispatch($club->uuid, $userUuid);

        return response()->noContent();
    }
}
