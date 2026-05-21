<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['user_one_uuid', 'user_two_uuid'])]
class DmConversation extends Model
{
    use HasUuids, SoftDeletes, HasFactory;

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
}
