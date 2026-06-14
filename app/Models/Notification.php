<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Notificación de la aplicación dirigida a un usuario.
 *
 * Soporta múltiples tipos (friend_request, club_invite, etc.)
 * con datos dinámicos almacenados en formato JSON.
 *
 * @property string $uuid UUID único de la notificación (PK)
 * @property string $user_uuid UUID del usuario destinatario (FK)
 * @property string $type Tipo de notificación
 * @property array|null $data Datos adicionales en formato JSON
 * @property bool $is_read Indica si la notificación fue leída
 * @property string|null $client_uuid UUID de deduplicación del frontend
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['user_uuid', 'type', 'data', 'is_read', 'client_uuid'])]
#[Hidden(['data'])]
class Notification extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

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
            'client_uuid' => 'string',
        ];
    }
}
