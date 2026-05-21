<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['club_uuid', 'name', 'sort_order', 'is_private'])]
class ClubCategories extends Model
{
    use HasUuids, SoftDeletes;
    protected $table = 'club_categories';
    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Una Categoria pertenece a un único Club.
    // FK ORIGEN: La llave foránea 'club_uuid' está físicamente en esta tabla 'club_categories'.
    // SQL: SELECT * FROM clubs WHERE uuid = club_categories.club_uuid LIMIT 1;
    public function club() {
        return $this->belongsTo(Club::class, 'club_uuid', 'uuid');
    }

}
