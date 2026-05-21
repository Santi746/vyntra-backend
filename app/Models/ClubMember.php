<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['user_uuid', 'club_uuid'])]
class ClubMember extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    // La tabla asignada es club_members
    protected $table = 'club_members';
    protected $primaryKey = 'uuid';


    // PERSPECTIVA: Una Membresía pertenece a un único Usuario.
    // FK ORIGEN: La llave foránea 'user_uuid' está físicamente en esta tabla 'club_members'.
    // SQL: SELECT * FROM users WHERE uuid = club_members.user_uuid LIMIT 1;
    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    // PERSPECTIVA: Una Membresía pertenece a un único Club.
    // FK ORIGEN: La llave foránea 'club_uuid' está físicamente en esta tabla 'club_members'.
    // SQL: SELECT * FROM clubs WHERE uuid = club_members.club_uuid LIMIT 1;
    public function club()
    {
        return $this->belongsTo(Club::class, 'club_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Miembro tiene muchos Roles asociados mediante una relación Muchos a Muchos.
    // TABLA PIVOTE: 'club_member_roles' (conecta club_member_uuid con role_uuid).
    // SQL: SELECT club_roles.* FROM club_roles 
    //      INNER JOIN club_member_roles ON club_member_roles.role_uuid = club_roles.uuid
    //      WHERE club_member_roles.club_member_uuid = club_members.uuid;
    public function roles()
    {
        return $this->belongsToMany(ClubRole::class, 'club_member_roles', 'club_member_uuid', 'role_uuid');
    }
}
