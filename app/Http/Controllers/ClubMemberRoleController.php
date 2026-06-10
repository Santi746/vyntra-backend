<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubMemberRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use App\Http\Requests\Club\AssignClubRoleRequest;

/**
 * Controlador de asignación de roles a miembros.
 *
 * Permite asignar y remover roles de un miembro específico
 * dentro de un club. La relación muchos-a-muchos entre
 * miembros y roles se gestiona aquí.
 */
class ClubMemberRoleController extends Controller
{
    public function store(AssignClubRoleRequest $request, Club $club): JsonResponse
    {
        Gate::authorize('create', [ClubMemberRole::class, $club]);

        $validated = $request->validated();

        $membership = ClubMember::where('user_uuid', $validated['user_uuid'])
            ->where('club_uuid', $club->uuid)
            ->firstOrFail();

        $memberRole = ClubMemberRole::firstOrCreate([
            'club_member_uuid' => $membership->uuid,
            'role_uuid' => $validated['role_uuid'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => null,
        ]);
    }
}
