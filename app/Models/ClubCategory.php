<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['club_uuid', 'name', 'sort_order', 'is_private'])]
class ClubCategory extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $table = 'club_categories';
    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Una Categoría pertenece a un único Club.
    // FK ORIGEN: La llave foránea 'club_uuid' está físicamente en esta tabla 'club_categories'.
    // SQL: SELECT * FROM clubs WHERE uuid = club_categories.club_uuid LIMIT 1;
    public function club()
    {
        return $this->belongsTo(Club::class, 'club_uuid', 'uuid');
    }

    // PERSPECTIVA: Una Categoría agrupa muchos Canales de chat/voz.
    // FK DESTINO: La llave foránea 'category_uuid' está físicamente en la tabla 'club_channels'.
    // SQL: SELECT * FROM club_channels WHERE category_uuid = club_categories.uuid;
    public function channels()
    {
        return $this->hasMany(ClubChannel::class, 'category_uuid', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'sort_order' => 'integer'
        ];
    }
}
