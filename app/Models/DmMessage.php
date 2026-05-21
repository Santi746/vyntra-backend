<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['dm_conversation_uuid', 'sender_uuid', 'parent_message_uuid', 'content', 'status', 'client_uuid'])]
class DmMessage extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

    protected $table = 'dm_messages';
    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Un Mensaje de MD pertenece a una única Conversación de MD.
    // FK ORIGEN: La llave foránea 'dm_conversation_uuid' está físicamente en esta tabla 'dm_messages'.
    // SQL: SELECT * FROM dm_conversations WHERE uuid = dm_messages.dm_conversation_uuid LIMIT 1;
    public function conversation()
    {
        return $this->belongsTo(DmConversation::class, 'dm_conversation_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Mensaje de MD pertenece a un único Usuario remitente (sender).
    // FK ORIGEN: La llave foránea 'sender_uuid' está físicamente en esta tabla 'dm_messages'.
    // SQL: SELECT * FROM users WHERE uuid = dm_messages.sender_uuid LIMIT 1;
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Mensaje de MD puede pertenecer a un Mensaje Padre (en caso de ser una respuesta).
    // FK ORIGEN: La llave foránea 'parent_message_uuid' está físicamente en esta tabla 'dm_messages'.
    // SQL: SELECT * FROM dm_messages WHERE uuid = dm_messages.parent_message_uuid LIMIT 1;
    public function parentMessage()
    {
        return $this->belongsTo(DmMessage::class, 'parent_message_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Mensaje de MD puede tener muchas Respuestas asociadas (hilo de conversación).
    // FK DESTINO: La llave foránea 'parent_message_uuid' está físicamente en la otra fila apuntando a esta.
    // SQL: SELECT * FROM dm_messages WHERE parent_message_uuid = dm_messages.uuid;
    public function replies()
    {
        return $this->hasMany(DmMessage::class, 'parent_message_uuid', 'uuid');
    }

    protected function casts(): array
    {
        return [
            'client_uuid' => 'string',
            'status' => 'string'
        ];
    }
}
