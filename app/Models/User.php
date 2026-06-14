<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ClubPermission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

// Trait obligatorio para UUIDs

/**
 * Modelo principal de usuario del sistema.
 *
 * @property string $uuid // Clave primaria
 * @property string $username
 * @property string $user_tag
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $bio
 * @property string|null $avatar_url
 * @property string|null $banner_url
 * @property string|null $location
 * @property bool $is_online
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property string|null $deleted_at
 * @property-read Collection<int, PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 *
 * @mixin \Eloquent
 */
#[Fillable(['username', 'user_tag', 'first_name', 'last_name', 'email', 'password', 'bio', 'avatar_url', 'banner_url', 'location', 'is_online'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, SoftDeletes;

    // Configurar explícitamente que la llave primaria es uuid
    protected $table = 'users'; // La tabla se llama users

    protected $primaryKey = 'uuid';

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

    /**
     * Verifica si el usuario tiene un permiso específico dentro de un club.
     *
     * El chequeo se hace en tres pasos:
     *   1. Owner bypass: si el usuario es dueño del club, siempre tiene el permiso.
     *   2. ADMINISTRATOR bypass: si el bitmask acumulado tiene ADMINISTRATOR, pasa todo.
     *   3. Suma los `permissions` de todos los roles asignados al miembro con OR bitwise
     *      y compara con AND contra el permiso solicitado.
     *
     * @param  int  $permission  Constante de \App\Enums\ClubPermission
     */
    public function hasClubPermission(Club $club, int $permission): bool
    {
        if ($club->owner_uuid === $this->uuid) {
            return true;
        }

        $membership = $this->memberships()
            ->where('club_uuid', $club->uuid)
            ->with(['roles' => fn ($q) => $q->select('club_roles.uuid', 'club_roles.permissions')])
            ->first();

        if (! $membership) {
            return false;
        }

        $bitmask = $membership->roles
            ->reduce(fn ($carry, $role) => $carry | (int) $role->permissions, 0);

        if (($bitmask & ClubPermission::ADMINISTRATOR) === ClubPermission::ADMINISTRATOR) {
            return true;
        }

        return ($bitmask & $permission) === $permission;
    }
}
