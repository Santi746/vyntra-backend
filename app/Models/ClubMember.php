<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Modelo de membresía de usuario en un club.
 *
 * Representa la pertenencia de un usuario a un club, con soporte
 * para soft-deletes, roles asociados y relaciones N:1 con User y Club.
 *
 *
 * @property string $uuid
 * @property string $user_uuid
 * @property string $club_uuid
 * @property Carbon|null $deleted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Club $club
 * @property-read Collection<int, ClubRole> $roles
 */
#[Fillable(['user_uuid', 'club_uuid'])]
class ClubMember extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'club_members';

    protected $primaryKey = 'uuid';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_uuid', 'uuid');
    }

    public function roles()
    {
        return $this->belongsToMany(ClubRole::class, 'club_member_roles', 'club_member_uuid', 'role_uuid');
    }
}
