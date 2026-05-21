# Laravel 13 Models - UUID & Architecture Rules

## 1. Mapeo Global de Modelos (Checklist del Sistema)
Cada tabla del esquema debe tener su modelo correspondiente en `app/Models/`:
- [x] `User` (Tabla: `users` - Adaptar a UUID)
- [x] `Friendship` (Tabla: `friendships`)
- [x] `Notification` (Tabla: `notifications`)
- [x] `Club` (Tabla: `clubs`)
- [x] `ClubMember` (Tabla: `club_members`)
- [x] `ClubRole` (Tabla: `club_roles`)
- [x] `ClubMemberRole` (Tabla: `club_member_roles`)
- [x] `ClubCategory` (Tabla: `club_categories`)
- [x] `ClubChannel` (Tabla: `club_channels`)
- [x] `ChannelMessage` (Tabla: `channel_messages`)
- [x] `DmConversation` (Tabla: `dm_conversations`)
- [x] `DmMessage` (Tabla: `dm_messages`)

---

## 2. Estructura Obligatoria de un Modelo (De Pies a Cabeza)

Todos los modelos creados en el sistema deben seguir esta estructura unificada en un solo bloque de código:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// PASO 1: Importar Traits Necesarios
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Generador automático de UUIDs
use Illuminate\Database\Eloquent\SoftDeletes;       // Si la tabla maneja borrado lógico

class EloquentModelTemplate extends Model
{
    // Activar los traits necesarios:
    use HasUuids;
    // use SoftDeletes;

    // =========================================================================
    // PASO 2: REGLAS DE CONFIGURACIÓN DE TABLA (CAMPOS PROTEGIDOS)
    // =========================================================================
    
    // Forzar el nombre exacto de la tabla de la base de datos
    protected $table = 'nombre_de_la_tabla'; 

    // Configurar nuestra llave primaria personalizada (UUID)
    protected $primaryKey = 'uuid';
    // Nota: HasUuids ya maneja 'incrementing = false' y 'keyType = string' por detrás.

    // Escudo de seguridad y Columnas Sensibles usando Atributos Modernos (Laravel 13+)
    // Colocar ANTES de la declaración de la clase:
    // #[Fillable(['client_uuid', 'columna_1', 'columna_2'])]
    // #[Hidden(['password', 'token_sensible'])]

    // =========================================================================
    // PASO 3: FUNCIONES DE RELACIÓN (ELOQUENT RELATIONSHIPS)
    // =========================================================================
    
    // Traducir las líneas del diagrama PostgreSQL usando camelCase. 
    // Obligatorio especificar llave foránea y llave local explícitamente.

    // Ejemplo: Un registro pertenece a un padre (BelongsTo)
    public function club()
    {
        return $this->belongsTo(Club::class, 'club_uuid', 'uuid');
    }

    // Ejemplo: Un padre tiene muchos hijos (HasMany)
    public function channels()
    {
        return $this->hasMany(ClubChannel::class, 'club_uuid', 'uuid');
    }

    // Ejemplo: Muchos a Muchos usando tablas pivote (BelongsToMany)
    public function roles()
    {
        return $this->belongsToMany(ClubRole::class, 'club_member_roles', 'club_member_uuid', 'role_uuid');
    }
}