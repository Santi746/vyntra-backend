# Lección 05: Middleware + Policies + Bitwise Permissions

> Lo que se hizo, por qué se hizo, y qué debes aprender de esto.

---

## 1. El problema que resolvimos

Antes de este paso, el backend tenía **dos capas de seguridad incompletas**:

1. **Middleware** (`auth:sanctum`) — ✅ Funcionaba. Verificaba que el usuario tuviera un token válido.
2. **Policies** — ❌ Incompletas. Solo 2 de 8 tenían lógica real. Los controllers que llamaban `Gate::authorize()` a policies vacías daban error 500.

Además, el sistema de **permisos bitwise** (roles con flags como `MANAGE_CHANNELS`, `KICK_MEMBERS`, etc.) solo existía en el frontend. El backend almacenaba el integer pero **nunca lo verificaba**. Esto creaba una **inconsistencia atómica**: el frontend permitía acciones a un moderador pero el backend las rechazaba porque solo aceptaba al owner.

---

## 2. Qué es una Policy (concepto)

Una Policy es una clase que responde a una pregunta: **"Este usuario autenticado, ¿puede hacer X acción en Y recurso?"**

```php
// ClubPolicy.php
public function update(User $user, Club $club): bool
{
    return $club->owner_uuid === $user->uuid;
    // true  → Laravel deja pasar al controller
    // false → Laravel devuelve 403 Forbidden automáticamente
}
```

### ¿Por qué Policies y no `if` inline en el controller?

```php
// ❌ ANTES (inline en el controller)
public function update(..., Club $club) {
    if ($club->owner_uuid !== $request->user()->uuid) {
        return response()->json(['error' => 'No autorizado'], 403);
    }
    // ... lógica
}

// ✅ DESPUÉS (Policy)
public function update(..., Club $club) {
    Gate::authorize('update', $club);  // 1 línea
    // ... lógica
}
```

Ventajas:
- **Centralizado**: la regla de "quién puede editar" vive en un solo archivo
- **Testeable**: puedes testear la Policy sin tocar el controller
- **Reutilizable**: si otro controller necesita la misma verificación, usa la misma Policy
- **Consistente**: todos los controllers usan el mismo patrón

---

## 3. Qué es el sistema Bitwise (concepto)

### El problema

Un club tiene múltiples roles (Owner, Admin, Moderator, Miembro). Cada rol tiene permisos distintos. ¿Cómo los almacenamos de forma eficiente?

### La solución: bitmask

Cada permiso es un **bit** en un número entero:

```
MANAGE_CHANNELS = 1 << 1 = 2  (binario: 00000010)
MANAGE_ROLES    = 1 << 2 = 4  (binario: 00000100)
KICK_MEMBERS    = 1 << 7 = 128 (binario: 10000000)
```

Un rol con permisos `MANAGE_CHANNELS + MANAGE_ROLES` tiene:
```
2 | 4 = 6 (binario: 00000110)
```

### Operadores bitwise

| Operador | Símbolo | Qué hace | Ejemplo |
|---|---|---|---|
| **OR** | `\|` | Activa bits | `2 | 4 = 6` (tiene ambos permisos) |
| **AND** | `&` | Verifica bits | `6 & 2 = 2` (¿tiene MANAGE_CHANNELS? Sí) |
| **XOR** | `^` | Toggle bit | `6 ^ 2 = 4` (quita MANAGE_CHANNELS) |

### Cómo se verifica un permiso

```php
$rolePermissions = 6;  // MANAGE_CHANNELS | MANAGE_ROLES
$required = 2;         // MANAGE_CHANNELS

if (($rolePermissions & $required) === $required) {
    // ✅ Tiene el permiso
}
```

### ¿Por qué `1 << 30` para ADMINISTRATOR?

`1 << 30 = 1073741824`. Es un bit alto para que no colisione con ningún otro permiso. Si un rol tiene este bit, **todos los permisos pasan** (bypass total):

```php
if (($bitmask & ADMINISTRATOR) === ADMINISTRATOR) {
    return true; // bypass: tiene todos los permisos
}
```

---

## 4. Archivos creados y modificados

### 4.1 `app/Enums/ClubPermission.php` (NUEVO)

**Qué es:** Una clase con constantes que definen cada permiso como un bit.

```php
class ClubPermission
{
    const VIEW_CHANNELS    = 1 << 0;   // 1
    const MANAGE_CHANNELS  = 1 << 1;   // 2
    const MANAGE_ROLES     = 1 << 2;   // 4
    const MANAGE_CLUB      = 1 << 3;   // 8
    const KICK_MEMBERS     = 1 << 7;   // 128
    const SEND_MESSAGES    = 1 << 9;   // 512
    const ADMINISTRATOR    = 1 << 30;  // 1073741824
}
```

**Por qué se creó:** Para que el backend tenga las mismas constantes que el frontend (`vyntra-frontend/src/shared/constants/permissions.js`). Sin esto, las Policies no sabrían qué bits verificar.

**Qué aprender:** Las constantes no son "mágicas" — cada valor es `1 << N` donde N es la posición del bit. Esto garantiza que cada permiso ocupa un bit único y no colisiona con otro.

---

### 4.2 `app/Models/User.php` (MODIFICADO)

**Qué se añadió:** El método `hasClubPermission()`:

```php
public function hasClubPermission(Club $club, int $permission): bool
{
    // 1. Owner bypass: el dueño siempre puede todo
    if ($club->owner_uuid === $this->uuid) {
        return true;
    }

    // 2. Buscar la membresía del usuario en el club
    $membership = $this->memberships()
        ->where('club_uuid', $club->uuid)
        ->with(['roles' => fn ($q) => $q->select('club_roles.uuid', 'club_roles.permissions')])
        ->first();

    if (!$membership) {
        return false;
    }

    // 3. Sumar los permisos de TODOS los roles con OR bitwise
    $bitmask = $membership->roles
        ->reduce(fn ($carry, $role) => $carry | (int) $role->permissions, 0);

    // 4. ADMINISTRATOR bypass
    if (($bitmask & ClubPermission::ADMINISTRATOR) === ClubPermission::ADMINISTRATOR) {
        return true;
    }

    // 5. Verificar con AND bitwise
    return ($bitmask & $permission) === $permission;
}
```

**Por qué se hizo:** Para centralizar la lógica de "¿este usuario tiene este permiso en este club?" en un solo método reutilizable. Todas las Policies llaman a este método en vez de repetir la lógica.

**Qué aprender:**

- **Owner bypass**: El dueño del club siempre pasa, sin importar sus roles. Esto es por diseño: el owner tiene control total.
- **Eager loading**: `with(['roles' => fn ($q) => ...])` carga los roles en una sola query, evitando N+1.
- **`select()`**: Solo pedimos `uuid` y `permissions` de los roles, no toda la fila. Optimización.
- **`reduce()`**: Itera sobre todos los roles y acumula los permisos con OR (`|`). Si un usuario tiene 2 roles con permisos `[2, 4]`, el resultado es `2 | 4 = 6`.
- **ADMINISTRATOR bypass**: Si el bitmask acumulado tiene el bit `1 << 30`, el usuario tiene todos los permisos (como root).

---

### 4.3 Las 8 Policies

#### `ClubPolicy` (ACTUALIZADA)

| Método | Antes | Después | Por qué |
|---|---|---|---|
| `viewAny` | `true` | `true` | Cualquier autenticado puede ver sus clubes |
| `view` | `members()->where()->exists()` | igual | Solo miembros ven detalles |
| `create` | `true` | `true` | Cualquier autenticado puede crear |
| `update` | `$club->owner_uuid === $user->uuid` | `hasClubPermission(MANAGE_CLUB)` | Ahora el owner + roles con MANAGE_CLUB pueden editar |
| `delete` | `$club->owner_uuid === $user->uuid` | igual | **Solo el owner** puede eliminar (demasiado destructivo para delegar) |
| `manageRoles` | *no existía* | `hasClubPermission(MANAGE_ROLES)` | Nuevo método para proteger gestión de roles |

**Por qué `update` cambió:** Antes solo el owner podía editar el club. Ahora un rol con `MANAGE_CLUB` también puede. El owner sigue pasando porque `hasClubPermission` tiene owner bypass.

**Por qué `delete` NO cambió:** Eliminar un club es la acción más destructiva. Solo el owner puede hacerlo, sin importar qué roles tenga. Esto es una decisión de diseño.

**Por qué `manageRoles` es nuevo:** `ClubRoleController` necesitaba autorización para crear/editar roles. Antes usaba `Gate::authorize('update', $club)` que era semánticamente incorrecto (update del club ≠ gestionar roles). Ahora usa `Gate::authorize('manageRoles', $club)`.

---

#### `ClubCategoryPolicy` (POBLADA)

```php
public function update(User $user, ClubCategory $category): bool
{
    return $user->hasClubPermission($category->club, ClubPermission::MANAGE_CHANNELS);
}

public function delete(User $user, ClubCategory $category): bool
{
    return $user->hasClubPermission($category->club, ClubPermission::MANAGE_CHANNELS);
}
```

**Por qué:** Solo usuarios con `MANAGE_CHANNELS` pueden editar/eliminar categorías. El owner pasa por bypass.

---

#### `ClubChannelPolicy` (POBLADA)

```php
public function update(User $user, ClubChannel $channel): bool
{
    return $user->hasClubPermission(
        $channel->category->club,
        ClubPermission::MANAGE_CHANNELS
    );
}
```

**Por qué:** Igual que categorías. El canal pertenece a una categoría que pertenece a un club, así que navegamos `$channel->category->club`.

---

#### `ClubMemberPolicy` (POBLADA)

```php
public function delete(User $user, Club $club): bool
{
    return $user->hasClubPermission($club, ClubPermission::KICK_MEMBERS);
}
```

**Nota importante:** El segundo parámetro es `Club`, no `ClubMember`. El controller pasa `[ClubMember::class, $club]`. Esto es porque la autorización se hace a nivel de club: "¿puede este usuario expulsar miembros de este club?"

---

#### `ClubMemberRolePolicy` (POBLADA)

```php
public function create(User $user, Club $club): bool
{
    return $user->hasClubPermission($club, ClubPermission::MANAGE_ROLES);
}
```

**Por qué:** Solo usuarios con `MANAGE_ROLES` pueden asignar roles a miembros.

---

#### `NotificationPolicy` (POBLADA)

```php
public function update(User $user, Notification $notification): bool
{
    return $notification->user_uuid === $user->uuid;
}
```

**Por qué no usa `hasClubPermission`:** Las notificaciones no están ligadas a un club. Es una verificación simple de ownership.

---

#### `DmConversationPolicy` (POBLADA)

```php
public function view(User $user, DmConversation $dm): bool
{
    return $dm->user_one_uuid === $user->uuid
        || $dm->user_two_uuid === $user->uuid;
}

public function create(User $user, DmConversation $dm): bool
{
    return $dm->user_one_uuid === $user->uuid
        || $dm->user_two_uuid === $user->uuid;
}
```

**Por qué:** Solo los participantes de la DM pueden verla o enviar mensajes. No hay roles ni permisos bitwise aquí — es una relación directa entre dos usuarios.

---

#### `ChannelMessagePolicy` (ACTUALIZADA)

| Método | Antes | Después |
|---|---|---|
| `viewAny` | Verifica membresía | Igual |
| `create` | Verifica membresía | `hasClubPermission(SEND_MESSAGES)` |

**Por qué cambió `create`:** Antes cualquier miembro del club podía enviar mensajes. Ahora necesita el permiso `SEND_MESSAGES` en su rol. El owner sigue pasando por bypass.

---

### 4.4 `app/Providers/AppServiceProvider.php` (MODIFICADO)

**Qué se añadió:** 6 registros de policies:

```php
Gate::policy(Club::class, ClubPolicy::class);
Gate::policy(ChannelMessage::class, ChannelMessagePolicy::class);
Gate::policy(ClubCategory::class, ClubCategoryPolicy::class);
Gate::policy(ClubChannel::class, ClubChannelPolicy::class);
Gate::policy(ClubMember::class, ClubMemberPolicy::class);
Gate::policy(ClubMemberRole::class, ClubMemberRolePolicy::class);
Gate::policy(DmConversation::class, DmConversationPolicy::class);
Gate::policy(Notification::class, NotificationPolicy::class);
```

**Por qué:** Laravel necesita saber qué Policy corresponde a qué modelo. Sin esto, `Gate::authorize('update', $category)` no sabe dónde buscar el método `update`.

**Nota:** Laravel 11 puede auto-descubrir policies por convención de nombre (`ModelPolicy` en `app/Policies/`). Pero registrarlas explícitamente es más claro y evita sorpresas.

---

### 4.5 Controllers modificados

#### `NotificationController@markAsRead`

```diff
- abort_if($notification->user_uuid !== $request->user()->uuid, 403);
+ Gate::authorize('update', $notification);
```

**Por qué:** `abort_if` es un check inline. `Gate::authorize` delega en `NotificationPolicy@update`, centralizando la regla.

#### `DmConversationController@show`

```diff
- abort_unless(
-     $dmConversation->user_one_uuid === $request->user()->uuid
-     || $dmConversation->user_two_uuid === $request->user()->uuid,
-     403
- );
+ Gate::authorize('view', $dmConversation);
```

**Por qué:** Mismo principio. La regla de "solo participantes" ahora vive en `DmConversationPolicy@view`.

#### `ClubController@show`

```php
Gate::authorize('view', $club);
```

**Por qué:** Antes cualquier usuario autenticado podía ver detalles de cualquier club. Ahora solo miembros pueden.

#### `DmMessageController@index` y `@store`

```php
Gate::authorize('view', $dmConversation);   // en index
Gate::authorize('create', $dmConversation); // en store
```

**Por qué:** Esto era un **hueco de seguridad**. Cualquier usuario autenticado que conociera el UUID de una DM podía leer y enviar mensajes. Ahora se verifica que el usuario sea participante.

---

## 5. Flujo completo de una petición (ahora)

```
📨 POST /api/clubs/{club}/categories
    │
    ▼
┌──────────────────────────────────────┐
│ 1. Ruta existe?                      │ ✅ Sí
└──────────────────────────────────────┘
    │
    ▼
┌──────────────────────────────────────┐
│ 2. Middleware auth:sanctum           │ ✅ Token válido → user autenticado
└──────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│ 3. Gate::authorize('create', $category)     │
│    → ClubCategoryPolicy@create($user, ...)  │
│    → $user->hasClubPermission($club,        │
│         ClubPermission::MANAGE_CHANNELS)    │
│    → Owner? → true (bypass)                 │
│    → Sino: roles tienen MANAGE_CHANNELS?    │
│      → Sí → true                            │
│      → No → false → 403                     │
└─────────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────────┐
│ 4. Form Request (validación)                │
└─────────────────────────────────────────────┘
    │
    ▼
┌──────────────────────────────┐
│ 5. Controller → Model → JSON │
└──────────────────────────────┘
```

---

## 6. Conceptos clave que debes aprender

### 6.1 Gate vs Policy

- **Gate**: Es el sistema de autorización de Laravel. `Gate::authorize()` es la puerta de entrada.
- **Policy**: Es una clase con métodos que Gate llama. Gate sabe qué Policy usar porque la registraste con `Gate::policy(Model::class, Policy::class)`.

### 6.2 ¿Qué diferencia hay entre 401 y 403?

| Código | Significado | Cuándo se devuelve |
|---|---|---|
| `401 Unauthorized` | No sé quién eres | Middleware `auth:sanctum` falla (sin token o token inválido) |
| `403 Forbidden` | Sé quién eres, pero no puedes hacer esto | Policy devuelve `false` |

### 6.3 ¿Por qué el orden importa?

Middleware → Policy → Form Request → Controller

Si la Policy falla (403), el Form Request nunca se ejecuta. Esto es correcto: primero verificamos si puedes hacer algo, luego validamos los datos.

### 6.4 Eager loading en `hasClubPermission`

```php
->with(['roles' => fn ($q) => $q->select('club_roles.uuid', 'club_roles.permissions')])
```

Esto evita el problema N+1. Sin el `with()`, cada vez que accedes a `$membership->roles` se haría una query adicional. Con el `with()`, todo se carga en una sola query.

El `select()` limita las columnas que se cargan. Solo necesitamos `uuid` y `permissions`, no `name`, `color`, `created_at`, etc.

### 6.5 `reduce()` para sumar bits

```php
$bitmask = $membership->roles
    ->reduce(fn ($carry, $role) => $carry | (int) $role->permissions, 0);
```

Esto es equivalente a:

```php
$bitmask = 0;
foreach ($membership->roles as $role) {
    $bitmask = $bitmask | (int) $role->permissions;
}
```

Si el usuario tiene 3 roles con permisos `[2, 4, 128]`:
```
0 | 2 = 2
2 | 4 = 6
6 | 128 = 134
```

El resultado `134` (binario: `10000110`) tiene los bits de MANAGE_CHANNELS, MANAGE_ROLES y KICK_MEMBERS activos.

---

## 7. Decisiones de diseño tomadas

| Decisión | Alternativa | Por qué elegimos esto |
|---|---|---|
| `ClubPermission` como clase con constantes | Enum backed | Más fácil para operaciones bitwise (los enums no soportan `<<` nativamente) |
| Owner bypass en `hasClubPermission` | Verificar owner en cada Policy | DRY: el bypass se define una vez, no en cada Policy |
| `delete` del club = owner-only | `hasClubPermission(MANAGE_CLUB)` | Eliminar un club es demasiado destructivo para delegar en roles |
| `manageRoles` como método nuevo en ClubPolicy | Crear `ClubRolePolicy` | Semánticamente correcto: la Policy del Club protege acciones sobre el club, incluyendo gestión de roles |
| `DmConversationPolicy` sin bitwise | Usar bitwise | Los DMs son 1-a-1, no tienen roles. La verificación es por participación directa. |
| Policies registradas explícitamente | Auto-discovery de Laravel 11 | Más claro y evita sorpresas si cambian las convenciones |

---

## 8. Qué NO se hizo (y por qué)

### Canales/categorías privadas (`is_private`)

`ChannelMessagePolicy@viewAny` no verifica si el canal es privado. Esto es pre-existente y requiere una decisión de diseño: ¿quién puede ver canales privados? ¿Solo miembros con un rol específico? Se deja para un paso futuro.

### Cache en `hasClubPermission`

El método hace una query cada vez que se llama. Si un endpoint llama `hasClubPermission` múltiples veces, se repite la query. Se puede optimizar con caché si el rendimiento lo demanda.

### Tests reescritos

Los tests existentes fueron escritos para el patrón `abort_if`/`abort_unless`. Con `Gate::authorize`, el comportamiento cambia (lanza `AuthorizationException` en vez de devolver 403 directamente). Reescribirlos usando `post()`/`get()` HTTP en vez de llamadas directas al controller es trabajo de un paso aparte.

---

## 9. Consistencia Frontend ↔ Backend

| Acción | Frontend verifica | Backend verifica |
|---|---|---|
| Editar categoría | `useCheckPermission(MANAGE_CHANNELS)` | `ClubCategoryPolicy@update` |
| Editar canal | `useCheckPermission(MANAGE_CHANNELS)` | `ClubChannelPolicy@update` |
| Enviar mensaje | `useCheckPermission(SEND_MESSAGES)` | `ChannelMessagePolicy@create` |
| Gestionar roles | `useCheckPermission(MANAGE_ROLES)` | `ClubPolicy@manageRoles` |
| Expulsar miembro | `useCheckPermission(KICK_MEMBERS)` | `ClubMemberPolicy@delete` |
| Editar club | `useCheckPermission(MANAGE_CLUB)` | `ClubPolicy@update` |
| Ver DM | `user === participant` | `DmConversationPolicy@view` |
| Ver notificación | `user === owner` | `NotificationPolicy@update` |

Ahora ambos lados verifican lo mismo. El backend ya no es un cuello de botella que rechaza acciones válidas del frontend.

---

## 10. Recursos para aprender más

- **Laravel Authorization**: https://laravel.com/docs/authorization
- **Bitwise operations en PHP**: https://www.php.net/manual/en/language.operators.bitwise.php
- **Discord permission system** (inspiración de este diseño): https://discord.com/developers/docs/topics/permissions
- **SOLID - Single Responsibility**: Cada Policy tiene una sola razón de cambio (las reglas de un modelo)
