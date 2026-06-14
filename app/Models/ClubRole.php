<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Rol personalizado dentro de un club.
 *
 * Los roles agrupan permisos bitwise y se asignan a miembros
 * del club para controlar qué acciones pueden realizar.
 *
 * @property string $uuid UUID único del rol (PK)
 * @property string $club_uuid UUID del club al que pertenece (FK)
 * @property string $name Nombre visible del rol
 * @property string $color Color hexadecimal del rol (ej: #FF0000)
 * @property bool $is_fixed Si el rol es fijo del sistema (no editable)
 * @property int $sort_order Orden de jerarquía del rol
 * @property int $permissions Permisos en formato bitmask
 * @property string|null $client_uuid UUID de deduplicación del frontend
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['club_uuid', 'name', 'color', 'is_fixed', 'sort_order', 'permissions', 'client_uuid'])]
class ClubRole extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'club_roles';

    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Un Rol pertenece a un único Club.
    // FK ORIGEN: La llave foránea 'club_uuid' está físicamente en esta tabla 'club_roles'.
    // SQL: SELECT * FROM clubs WHERE uuid = club_roles.club_uuid LIMIT 1;
    public function club()
    {
        return $this->belongsTo(Club::class, 'club_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Rol puede estar asignado a muchos Miembros mediante una relación Muchos a Muchos.
    // TABLA PIVOTE: 'club_member_roles' (conecta role_uuid con club_member_uuid).
    // SQL: SELECT club_members.* FROM club_members
    //      INNER JOIN club_member_roles ON club_member_roles.club_member_uuid = club_members.uuid
    //      WHERE club_member_roles.role_uuid = club_roles.uuid;
    public function members()
    {
        return $this->belongsToMany(ClubMember::class, 'club_member_roles', 'role_uuid', 'club_member_uuid');
    }

    protected function casts(): array
    {
        return [
            'is_fixed' => 'boolean',
            'sort_order' => 'integer',
            'permissions' => 'integer',
        ];
    }
}
