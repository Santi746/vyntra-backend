<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['sender_uuid', 'receiver_uuid', 'status'])]
class Friendship extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'friendships'; // La tabla se llama friendships

    protected $primaryKey = 'uuid'; // La llave primaria es uuid

    // PERSPECTIVA: Una Relación de Amistad pertenece al Usuario que envió la solicitud (sender).
    // FK ORIGEN: La llave foránea 'sender_uuid' está físicamente en esta tabla 'friendships'.
    // SQL: SELECT * FROM users WHERE uuid = friendships.sender_uuid LIMIT 1;
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_uuid', 'uuid');
    }

    // PERSPECTIVA: Una Relación de Amistad pertenece al Usuario que recibió la solicitud (receiver).
    // FK ORIGEN: La llave foránea 'receiver_uuid' está físicamente en esta tabla 'friendships'.
    // SQL: SELECT * FROM users WHERE uuid = friendships.receiver_uuid LIMIT 1;
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_uuid', 'uuid');
    }
}
