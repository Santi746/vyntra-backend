<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Añade índices compuestos bidireccionales a club_member_roles.
 *
 * Sin estos índices, cada carga de un miembro con sus roles (ClubMember::with('roles'))
 * o de un rol con sus miembros (ClubRole::with('members')) escanea la tabla pivote
 * completa. Con escala, esto degrada el sidebar de miembros y la asignación de roles.
 *
 * Índice 1: (club_member_uuid, role_uuid)  — para cargar roles de un miembro.
 * Índice 2: (role_uuid, club_member_uuid)  — para cargar miembros de un rol.
 *
 * @see docs/architecture/database_scalability_rules.md §4
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_member_roles', function (Blueprint $table) {
            $table->index(['club_member_uuid', 'role_uuid']);
            $table->index(['role_uuid', 'club_member_uuid']);
        });
    }

    public function down(): void
    {
        Schema::table('club_member_roles', function (Blueprint $table) {
            $table->dropIndex(['club_member_uuid', 'role_uuid']);
            $table->dropIndex(['role_uuid', 'club_member_uuid']);
        });
    }
};
