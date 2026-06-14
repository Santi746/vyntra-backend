<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Categoría de canales dentro de un club.
 *
 * Agrupa canales de chat/voz en secciones organizadas
 * (ej: "General", "Comunidad", "Gaming").
 *
 * @property string $uuid UUID único de la categoría (PK)
 * @property string $club_uuid UUID del club al que pertenece (FK)
 * @property string $name Nombre visible de la categoría
 * @property int $sort_order Orden de visualización en la UI
 * @property bool $is_private Si la categoría es privada
 * @property string|null $client_uuid UUID de deduplicación del frontend
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['club_uuid', 'name', 'sort_order', 'is_private', 'client_uuid'])]
class ClubCategory extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

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
            'sort_order' => 'integer',
        ];
    }
}
