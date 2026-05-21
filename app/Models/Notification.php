<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['user_uuid', 'type', 'data', 'is_read', 'client_uuid'])]
#[Hidden(['data'])]
class Notification extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    // La tabla asignada es notifications
    protected $table = 'notifications';
    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Una Notificación pertenece al Usuario destinatario (el que la recibe).
    // FK ORIGEN: La llave foránea 'user_uuid' está físicamente en esta tabla 'notifications'.
    // SQL: SELECT * FROM users WHERE uuid = notifications.user_uuid LIMIT 1;
    public function notificationReceiver()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    // le indica que el tipo de dato de data es un ARRAY (pero su flujo sera en un JSON)
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
            'client_uuid' => 'string'
        ];
    }
}
