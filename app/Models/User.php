<?php

namespace App\Models;

use App\Enums\ClubPermission;
use App\Models\Concerns\ValidatesUuidRouteBinding;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;

// Trait obligatorio para UUIDs

/**
 * Modelo principal de usuario del sistema.
 *
 * @property string $uuid // Clave primaria
 * @property string|null $username // único. Null temporalmente tras OAuth hasta que complete-profile
 * @property string|null $first_name // Null temporalmente tras OAuth hasta que complete-profile
 * @property string|null $last_name // Null temporalmente tras OAuth hasta que complete-profile
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
#[Fillable([
    'username',
    'first_name',
    'last_name',
    'email',
    'password',
    'bio',
    'avatar_url',
    'banner_url',
    'location',
    'is_online',
    // --- Socialite ---
    'provider',
    'provider_id',
    'provider_token',
    'provider_refresh_token',
    // --- 2FA TOTP ---
    'two_factor_secret',
    'two_factor_enabled',
    'two_factor_backup_codes',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, MustVerifyEmail, Notifiable, SoftDeletes, ValidatesUuidRouteBinding;

    // Configurar explícitamente que la llave primaria es uuid
    protected $table = 'users'; // La tabla se llama users

    protected $primaryKey = 'uuid';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            // --- 2FA TOTP ---
            'two_factor_enabled' => 'boolean', // si esta activo el 2fa o no
            'two_factor_backup_codes' => 'array',
        ];
    }

    // / Logica de AUTH 2FA,TOTP,Backup Code

    /**
     * Verifica si el usuario tiene 2FA activo y confirmado.
     *
     * Retorna true SOLO si:
     *  - two_factor_enabled = true (el usuario confirmó 2FA con un primer código TOTP)
     *  - Tiene un secret guardado (two_factor_secret no es null)
     */
    public function has2faEnabled(): bool
    {
        return $this->two_factor_enabled && $this->two_factor_secret !== null;
    }

    /**
     * Verifica un código TOTP contra el secret del usuario.
     *
     * Usa el paquete pragmarx/google2fa con una ventana de ±1 paso
     * (aprox. 90 segundos de tolerancia) para compensar diferencias
     * de reloj entre el servidor y el teléfono del usuario.
     *
     * @param  string  $code  Código de 6 dígitos ingresado por el usuario
     */
    public function verifyTwoFactorCode(string $code): bool
    {
        if (! $this->two_factor_secret) {
            return false;
        }

        $google2fa = new Google2FA;

        return $google2fa->verifyKey(
            $this->two_factor_secret,
            $code,
            1  // ventana de tolerancia: ±1 paso (30s antes, 30s después)
        );
    }

    /**
     * Verifica y consume un backup code.
     *
     * Recorre el array de backup codes, compara cada uno con el código
     * ingresado (usando Hash::check porque los códigos se guardan
     * hasheados con bcrypt). Si encuentra coincidencia:
     *  1. Elimina ese código del array
     *  2. Guarda el array actualizado en la DB
     *  3. Retorna true
     *
     * @param  string  $code  Backup code ingresado (formato: XXXXXXXX)
     */
    public function useBackupCode(string $code): bool
    {
        $backupCodes = $this->two_factor_backup_codes ?? [];

        foreach ($backupCodes as $index => $hashedCode) {
            if (Hash::check($code, $hashedCode)) {
                // Eliminar el código usado
                unset($backupCodes[$index]);
                $this->two_factor_backup_codes = array_values($backupCodes);
                $this->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Genera 10 backup codes hasheados y los guarda en el modelo.
     *
     * @return array<string> Códigos en texto plano (se muestran una sola vez al usuario)
     */
    public function generateBackupCodes(): array
    {
        $plainCodes = [];
        $hashedCodes = [];

        for ($i = 0; $i < 10; $i++) {
            $plain = strtoupper(Str::random(8));
            $plainCodes[] = $plain;
            $hashedCodes[] = Hash::make($plain);
        }

        $this->two_factor_backup_codes = $hashedCodes;

        return $plainCodes;
    }

    /**
     * Desactiva 2FA: limpia secret, flag y backup codes.
     */
    public function disableTwoFactor(): void
    {
        $this->two_factor_secret = null;
        $this->two_factor_enabled = false;
        $this->two_factor_backup_codes = null;
        $this->save();
    }

    // / --- RELACIONES SQL CON QUERIES ---

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

    // / Verificacion de Permisos de usuario

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
