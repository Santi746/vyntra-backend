<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Canal de chat o voz dentro de una categoría de club.
 *
 * Los canales de texto permiten enviar mensajes; los de voz
 * están diseñados para comunicación por audio en tiempo real.
 *
 * @property string $uuid UUID único del canal (PK)
 * @property string $category_uuid UUID de la categoría padre (FK)
 * @property string $name Nombre del canal
 * @property string|null $description Descripción del canal
 * @property string $type Tipo de canal: "text" o "voice"
 * @property int $sort_order Orden de visualización en la UI
 * @property bool $is_private Si el canal es privado
 * @property string|null $client_uuid UUID de deduplicación del frontend
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ClubChannel query()
 */
#[Fillable(['category_uuid', 'name', 'description', 'type', 'sort_order', 'is_private', 'client_uuid'])]
class ClubChannel extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $table = 'club_channels';
    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Un Canal pertenece a una única Categoría que lo agrupa.
    // FK ORIGEN: La llave foránea 'category_uuid' está físicamente en esta tabla 'club_channels'.
    // SQL: SELECT * FROM club_categories WHERE uuid = club_channels.category_uuid LIMIT 1;
    public function category()
    {
        return $this->belongsTo(ClubCategory::class, 'category_uuid', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
            'sort_order' => 'integer'
        ];
    }
}
