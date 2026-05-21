<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

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
