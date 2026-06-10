<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade el índice compuesto (user_uuid, created_at) a club_members.
 *
 * Necesario para que la paginación por cursor del endpoint
 * GET /api/user/clubs (ClubController@index) escale correctamente.
 * Sin este índice, la consulta que hace $request->user()->memberships()
 * filtrada por user_uuid y ordenada por created_at DESC degenera a O(n).
 *
 * @see docs/architecture/database_scalability_rules.md §4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->index(['user_uuid', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table) {
            $table->dropIndex(['user_uuid', 'created_at']);
        });
    }
};
