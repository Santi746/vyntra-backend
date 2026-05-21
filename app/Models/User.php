<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Trait obligatorio para UUIDs

/**
 * @property string $uuid // Clave primaria
 * @property string $username
 * @property string $user_tag
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $bio
 * @property string|null $avatar_url
 * @property string|null $banner_url
 * @property string|null $location
 * @property bool $is_online
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereAvatarUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBannerUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereBio($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereIsOnline($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereLocation($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUserTag($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereUuid($value)
 * @property string|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|User whereDeletedAt($value)
 * @mixin \Eloquent
 */
#[Fillable(['username', 'user_tag', 'first_name', 'last_name', 'email', 'password', 'bio', 'avatar_url', 'banner_url', 'location', 'is_online'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, Notifiable, SoftDeletes, HasUuids;

    // Configurar explícitamente que la llave primaria es uuid
    protected $table = 'users'; // La tabla se llama users
    protected $primaryKey = 'uuid';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // PERSPECTIVA: Un Usuario tiene muchas Solicitudes de Amistad enviadas por él.
    // FK DESTINO: La llave foránea 'sender_uuid' está físicamente en la tabla 'friendships'.
    // SQL: SELECT * FROM friendships WHERE sender_uuid = users.uuid;
    public function sentFriendships()
    {
        return $this->hasMany(Friendship::class, 'sender_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Usuario tiene muchas Solicitudes de Amistad recibidas por él.
    // FK DESTINO: La llave foránea 'receiver_uuid' está físicamente en la tabla 'friendships'.
    // SQL: SELECT * FROM friendships WHERE receiver_uuid = users.uuid;
    public function receivedFriendships()
    {
        return $this->hasMany(Friendship::class, 'receiver_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Usuario puede ser dueño de muchos Clubes.
    // FK DESTINO: La llave foránea 'owner_uuid' está físicamente en la tabla 'clubs'.
    // SQL: SELECT * FROM clubs WHERE owner_uuid = users.uuid;
    public function ownedClubs()
    {
        return $this->hasMany(Club::class, 'owner_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Usuario tiene muchas Membresías de Clubes a los que se ha unido.
    // FK DESTINO: La llave foránea 'user_uuid' está físicamente en la tabla 'club_members'.
    // SQL: SELECT * FROM club_members WHERE user_uuid = users.uuid;
    public function memberships()
    {
        return $this->hasMany(ClubMember::class, 'user_uuid', 'uuid');
    }

    // PERSPECTIVA: Un Usuario tiene muchas Notificaciones de la aplicación.
    // FK DESTINO: La llave foránea 'user_uuid' está físicamente en la tabla 'notifications'.
    // SQL: SELECT * FROM notifications WHERE user_uuid = users.uuid;
    // NOTA: Se nombra 'appNotifications' para no colisionar con el trait 'Notifiable' de Laravel.
    public function appNotifications()
    {
        return $this->hasMany(Notification::class, 'user_uuid', 'uuid');
    }
}
