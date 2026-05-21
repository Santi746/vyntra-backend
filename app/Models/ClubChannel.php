<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['category_uuid', 'name', 'description', 'type', 'sort_order', 'is_private'])]
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
