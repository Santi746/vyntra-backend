<?php

namespace App\Models;

use App\Models\Concerns\ValidatesUuidRouteBinding;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Relación pivote entre un miembro y un rol en un club.
 *
 * Tabla Many-to-Many que asigna roles específicos a miembros.
 *
 * @property string $uuid UUID único de la asignación (PK)
 * @property string $club_member_uuid UUID del miembro (FK)
 * @property string $role_uuid UUID del rol asignado (FK)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['club_member_uuid', 'role_uuid'])]
class ClubMemberRole extends Model
{
    use HasFactory, HasUuids, ValidatesUuidRouteBinding;

    protected $table = 'club_member_roles';

    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Esta fila de la tabla pivote pertenece a un único Miembro del Club.
    // FK ORIGEN: La llave foránea 'club_member_uuid' está físicamente en esta tabla 'club_member_roles'.
    // SQL: SELECT * FROM club_members WHERE uuid = club_member_roles.club_member_uuid LIMIT 1;
    public function member()
    {
        return $this->belongsTo(ClubMember::class, 'club_member_uuid', 'uuid');
    }

    // PERSPECTIVA: Esta fila de la tabla pivote pertenece a un único Rol de Club.
    // FK ORIGEN: La llave foránea 'role_uuid' está físicamente en esta tabla 'club_member_roles'.
    // SQL: SELECT * FROM club_roles WHERE uuid = club_member_roles.role_uuid LIMIT 1;
    public function role()
    {
        return $this->belongsTo(ClubRole::class, 'role_uuid', 'uuid');
    }
}
