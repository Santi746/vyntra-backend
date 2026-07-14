<?php

namespace App\Models;

use App\Models\Concerns\ValidatesUuidRouteBinding;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'description', 'avatar_url', 'banner_url', 'owner_uuid', 'category_tag', 'client_uuid'])]
class Club extends Model
{
    use HasFactory, HasUuids, SoftDeletes, ValidatesUuidRouteBinding;

    // la tabla asignada es clubs
    protected $table = 'clubs';

    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Un Club pertenece a un único Usuario creador (dueño).
    // FK ORIGEN: La llave foránea 'owner_uuid' está físicamente en esta tabla 'clubs'.
    // SQL: SELECT * FROM users WHERE uuid = clubs.owner_uuid LIMIT 1;
    public function clubOwner()
    {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Club tiene muchas Categorías asociadas para organizar sus canales.
    // FK DESTINO: La llave foránea 'club_uuid' está físicamente en la tabla 'club_categories'.
    // SQL: SELECT * FROM club_categories WHERE club_uuid = clubs.uuid;
    public function categories()
    {
        return $this->hasMany(ClubCategory::class, 'club_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Club tiene muchos Miembros asociados.
    // FK DESTINO: La llave foránea 'club_uuid' está físicamente en la otra tabla 'club_members'.
    // SQL: SELECT * FROM club_members WHERE club_uuid = clubs.uuid;
    public function members()
    {
        return $this->hasMany(ClubMember::class, 'club_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Club tiene muchos Roles creados para su administración.
    // FK DESTINO: La llave foránea 'club_uuid' está físicamente en la otra tabla 'club_roles'.
    // SQL: SELECT * FROM club_roles WHERE club_uuid = clubs.uuid;
    public function roles()
    {
        return $this->hasMany(ClubRole::class, 'club_uuid', 'uuid');
    }
}
