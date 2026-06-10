<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Mensaje dentro de un canal de club.
 *
 * Soporta respuestas en hilo mediante parent_message_uuid
 * y deduplicación en tiempo real mediante client_uuid
 * con restricción UNIQUE compuesta por canal.
 *
 * @property string $uuid UUID único del mensaje (PK)
 * @property string $club_channel_uuid UUID del canal donde se envió (FK)
 * @property string $sender_uuid UUID del remitente (FK)
 * @property string|null $parent_message_uuid UUID del mensaje padre (respuesta) (FK)
 * @property string $content Contenido del mensaje
 * @property string $status Estado del mensaje (sent, edited)
 * @property string $client_uuid UUID de deduplicación del frontend
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ChannelMessage query()
 */
#[Fillable(['club_channel_uuid', 'sender_uuid', 'parent_message_uuid', 'content', 'status', 'client_uuid'])]
class ChannelMessage extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $table = 'channel_messages';
    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Un Mensaje de Canal pertenece a un único Canal del Club.
    // FK ORIGEN: La llave foránea 'club_channel_uuid' está físicamente en esta tabla 'channel_messages'.
    // SQL: SELECT * FROM club_channels WHERE uuid = channel_messages.club_channel_uuid LIMIT 1;
    public function channel()
    {
        return $this->belongsTo(ClubChannel::class, 'club_channel_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Mensaje de Canal pertenece a un único Usuario remitente (sender).
    // FK ORIGEN: La llave foránea 'sender_uuid' está físicamente en esta tabla 'channel_messages'.
    // SQL: SELECT * FROM users WHERE uuid = channel_messages.sender_uuid LIMIT 1;
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Mensaje de Canal puede pertenecer a un Mensaje Padre (en caso de ser una respuesta).
    // FK ORIGEN: La llave foránea 'parent_message_uuid' está físicamente en esta tabla 'channel_messages'.
    // SQL: SELECT * FROM channel_messages WHERE uuid = channel_messages.parent_message_uuid LIMIT 1;
    public function parentMessage()
    {
        return $this->belongsTo(ChannelMessage::class, 'parent_message_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Mensaje de Canal puede tener muchas Respuestas asociadas (hilo de conversación).
    // FK DESTINO: La llave foránea 'parent_message_uuid' está físicamente en la otra fila apuntando a esta.
    // SQL: SELECT * FROM channel_messages WHERE parent_message_uuid = channel_messages.uuid;
    public function replies()
    {
        return $this->hasMany(ChannelMessage::class, 'parent_message_uuid', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'client_uuid' => 'string',
            'status' => 'string'
        ];
    }
}
