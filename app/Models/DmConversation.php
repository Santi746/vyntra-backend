<?php

namespace App\Models;

use App\Models\Concerns\ValidatesUuidRouteBinding;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_one_uuid', 'user_two_uuid'])]
class DmConversation extends Model
{
    use HasFactory, HasUuids, SoftDeletes, ValidatesUuidRouteBinding;

    protected $table = 'dm_conversations';

    protected $primaryKey = 'uuid';

    // PERSPECTIVA: Una Conversación de MD pertenece al primer Usuario participante.
    // FK ORIGEN: La llave foránea 'user_one_uuid' está físicamente en esta tabla 'dm_conversations'.
    // SQL: SELECT * FROM users WHERE uuid = dm_conversations.user_one_uuid LIMIT 1;
    public function userOne()
    {
        return $this->belongsTo(User::class, 'user_one_uuid', 'uuid');
    }

    // PERSPECTIVA: Una Conversación de MD pertenece al segundo Usuario participante.
    // FK ORIGEN: La llave foránea 'user_two_uuid' está físicamente en esta tabla 'dm_conversations'.
    // SQL: SELECT * FROM users WHERE uuid = dm_conversations.user_two_uuid LIMIT 1;
    public function userTwo()
    {
        return $this->belongsTo(User::class, 'user_two_uuid', 'uuid');
    }

    // PERSPECTIVA: Una Conversación de MD agrupa muchos Mensajes de MD.
    // FK DESTINO: La llave foránea 'dm_conversation_uuid' está físicamente en la tabla 'dm_messages'.
    // SQL: SELECT * FROM dm_messages WHERE dm_conversation_uuid = dm_conversations.uuid;
    public function messages()
    {
        return $this->hasMany(DmMessage::class, 'dm_conversation_uuid', 'uuid');
    }

    // PERSPECTIVA: Una Conversación tiene un último mensaje (el más reciente).
    // Se usa en DmConversationResource para mostrar la preview en la lista de chats.
    // SQL: SELECT * FROM dm_messages WHERE dm_conversation_uuid = dm_conversations.uuid ORDER BY created_at DESC LIMIT 1;
    public function lastMessage()
    {
        return $this->hasOne(DmMessage::class, 'dm_conversation_uuid', 'uuid')
            ->orderBy('created_at', 'desc');
    }
}
