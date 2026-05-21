<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['club_uuid', 'name', 'color', 'is_fixed', 'permissions'])]
class ClubRole extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    // Se le asigna la tabla 'club_roles'
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
            'permissions' => 'integer'
        ];
    }
}
