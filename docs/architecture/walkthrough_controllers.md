# 🚶 Walkthrough: Implementación Completa de Controllers Vyntra

> **Propósito:** Explicar línea por línea cada controller, resource, y modelo tocado, por qué se hizo así, y qué lecciones quedan.

---

## 📐 Los 7 Patrones de Controller (Versión Ejecutable)

Antes de leer los controllers, tenés que entender que **NO existe un solo patrón**. Hay 7 variantes, cada una con su sintaxis específica:

| # | Patrón | Métodos | Formato respuesta | Código HTTP |
|---|--------|---------|-------------------|-------------|
| 1 | Objeto individual | `show()`, `me()` | `{status, data}` | 200 |
| 2 | Creación simple | `store()` (join, simple) | `{status, data}` | 201 |
| 3 | Creación idempotente | `store()` (firstOrCreate) | `{status, data}` | 201 o 200 |
| 4 | Actualización | `update()` | `{status, data}` | 200 |
| 5 | Eliminación | `destroy()` | `response()->noContent()` | 204 |
| 6 | Lista paginada | `index()` | `{data, meta}` | 200 |
| 7 | Autenticación | `login()`, `register()`, `logout()` | `{status, data}` | 200/201 |

**Regla de oro:** El controller es coordinador, NO obrero. No valida, no escribe SQL raw, no formatea JSON. Solo orquesta: recibe datos ya validados del FormRequest, usa el Modelo, delega al Resource.

---

## 1. 🐛 Bugs Corregidos en Resources

### 1.1 ClubResource — `logo_url` → `avatar_url`

**Archivo:** `app/Http/Resources/ClubResource.php:17`

**Antes:**
```php
'logo_url' => $this->logo_url,
```

**Después:**
```php
'avatar_url' => $this->avatar_url,
```

**Por qué:** La columna en la migración y el `$fillable` del modelo Club usan `avatar_url`, no `logo_url`. El Resource devolvía `null` en ese campo porque `$this->logo_url` no existe en la BD. El frontend (que espera `avatar_url`) recibía `null` siempre.

**Lección:** El Resource debe reflejar EXACTAMENTE los nombres de columna de la BD. Si el frontend espera un nombre distinto, se mapea en el Resource pero el acceso al modelo debe usar el nombre real de la columna.

---

### 1.2 DmConversationResource — `user1` → `userOne`, `user1_uuid` → `user_one_uuid`

**Archivo:** `app/Http/Resources/DmConversationResource.php:17-25`

**Antes:**
```php
$this->relationLoaded('user1') || $this->relationLoaded('user2')
// ...
if ($currentUser && $this->user1_uuid === $currentUser->uuid) {
    return $this->user2;
}
return $this->user1;
```

**Después:**
```php
$this->relationLoaded('userOne') || $this->relationLoaded('userTwo')
// ...
if ($currentUser && $this->user_one_uuid === $currentUser->uuid) {
    return $this->userTwo;
}
return $this->userOne;
```

**Por qué:** El modelo `DmConversation` define las relaciones como `userOne()` y `userTwo()`, y las columnas como `user_one_uuid` y `user_two_uuid`. El Resource estaba accediendo a relaciones y columnas que **no existen**, lo que disparaba consultas SQL incorrectas o errores silenciosos.

**Lección:** Los nombres de relaciones en el modelo (ej: `userOne()`) DEFINEN cómo se accede desde fuera (`$this->userOne`). El Resource debe usar los mismos nombres que el modelo, no inventar alias.

---

### 1.3 FriendshipResource — `recipient` → `receiver`

**Archivo:** `app/Http/Resources/FriendshipResource.php:31`

**Antes:** `new UserResource($this->whenLoaded('recipient'))`
**Después:** `new UserResource($this->whenLoaded('receiver'))`

**Por qué:** La relación en el modelo `Friendship` se llama `receiver()`, no `recipient()`. Estaba accediendo a una relación que no existe, devolviendo `null` siempre.

**Lección:** Las relaciones de Eloquent se nombran en el modelo y se usan IGUAL en todas partes. No hay traducción automática.

---

### 1.4 SessionResource — Mapeo de campos reales de Sanctum

**Archivo:** `app/Http/Resources/SessionResource.php`

**Antes:**
```php
'uuid' => $this->uuid,           // PersonalAccessToken no tiene uuid, tiene id autoincrement
'os' => $this->os,               // No existe en la tabla
'browser' => $this->browser,     // No existe
'ip' => $this->ip,               // No existe
'location' => $this->location,   // No existe
'is_current' => (bool) $this->is_current,  // No existe
'type' => $this->type,           // No existe
```

**Después:**
```php
'uuid' => $this->ulid ?? (string) $this->id,  // Usa id como fallback
'os' => $this->os,                             // Sigue siendo null sin migration
'browser' => $this->browser,
'ip' => $this->ip,
'location' => $this->location,
'is_current' => $request->user()?->currentAccessToken()?->id === $this->id,
'type' => $this->name,                          // Mapea al campo real 'name'
```

**Por qué:** Sanctum guarda los tokens en `personal_access_tokens` con columnas: `id`, `tokenable_type`, `tokenable_id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`. No tiene `os`, `browser`, `ip`, `location`. El `is_current` se calcula comparando el ID del token actual con el de la iteración. El `type` se mapea de `name` (que es "auth_token" o similar).

**Pendiente:** Para tener `os`, `browser`, `ip`, `location` reales, se necesita una migración que agregue esas columnas a `personal_access_tokens` y lógica en login que capture el User-Agent. Sin eso, estos campos devuelven `null`.

---

### 1.5 ClubMemberResource — Eliminación de `category_tag`

**Archivo:** `app/Http/Resources/ClubMemberResource.php:52`

**Antes:** `'category_tag' => $user?->category_tag,`
**Después:** Eliminado

**Por qué:** El modelo `User` NO tiene un campo `category_tag`. Esa columna existe en `clubs`, no en `users`. El Resource accedía a una propiedad inexistente, devolviendo siempre `null`.

---

### 1.6 UserController@updateProfile — Eliminación de `unset()` hack

**Archivo:** `app/Http/Controllers/UserController.php:60-61`

**Antes:**
```php
$validated = $request->validated();
unset($validated['current_password'], $validated['new_password']);
$request->user()->update($validated);
```

**Después:**
```php
$validated = $request->validated();
$request->user()->update($validated);
```

**Por qué:** Si `current_password` y `new_password` están siendo validados por el FormRequest, es porque el FormRequest los incluye en `rules()`. Pero si llegan al controller y se hace `unset()`, hay dos problemas:
1. El controller está haciendo lógica de negocio (decidir qué campos pasar al modelo), que no es su responsabilidad
2. Si el FormRequest valida campos que luego se descartan, la validación es mentirosa

La solución correcta es que el FormRequest solo valide los campos que realmente se van a persistir. Si se necesita cambio de password, debe tener su propio endpoint o lógica separada.

---

## 2. 🧩 Modelos Modificados

### 2.1 DmConversation — Agregado `lastMessage()`

**Archivo:** `app/Models/DmConversation.php:46-49`

```php
public function lastMessage()
{
    return $this->hasOne(DmMessage::class, 'dm_conversation_uuid', 'uuid')
        ->latestOfMany();
}
```

**Por qué:** El `DmConversationResource` usa `$this->whenLoaded('lastMessage')` para mostrar la preview del último mensaje en la lista de chats. Sin esta relación en el modelo, el Resource siempre devolvía `null`.

**`latestOfMany()`** es un método de Eloquent que equivale a: `ORDER BY created_at DESC LIMIT 1`. Carga solo el mensaje más reciente de la conversación, sin traerlos todos.

**Uso en controller:**
```php
// DmConversationController@index
->with(['userOne', 'userTwo', 'lastMessage'])
```

Esto hace 4 consultas SQL totales (conversaciones + userOne + userTwo + último mensaje), SIN problema N+1.

---

## 3. 🆕 Controllers Nuevos (9 Implementados)

### 3.1 ClubCategoryController

**Archivo:** `app/Http/Controllers/ClubCategoryController.php`

**Métodos:** `index()`, `store()`, `update()`, `destroy()`

**`index(Club $club)` — Lista categorías con canales:**
```php
$categories = ClubCategory::where('club_uuid', $club->uuid)
    ->with('channels')
    ->orderBy('sort_order', 'asc')
    ->cursorPaginate(15);
```

- `where('club_uuid', $club->uuid)` → filtra por el club del parámetro de ruta
- `->with('channels')` → eager loading de los canales de cada categoría (evita N+1)
- `->orderBy('sort_order', 'asc')` → orden estable requerido por cursorPaginate
- `->cursorPaginate(15)` → paginación por cursor, NUNCA offset

**`store(StoreClubCategoryRequest $request, Club $club)` — Crea categoría con idempotencia:**
```php
$category = ClubCategory::firstOrCreate(
    ['club_uuid' => $club->uuid, 'client_uuid' => $validated['client_uuid']],
    [
        'name' => $validated['name'],
        'sort_order' => $validated['sort_order'] ?? (ClubCategory::where('club_uuid', $club->uuid)->max('sort_order') ?? 0) + 1,
        'is_private' => $validated['is_private'] ?? false,
    ]
);
```

- `firstOrCreate([condición], [valores])` → busca por `(club_uuid, client_uuid)`. Si existe, lo devuelve. Si no, lo crea con los valores.
- `sort_order` se auto-asigna como el máximo existente + 1, o 0 si no hay categorías
- `$category->wasRecentlyCreated` → true si firstOrCreate creó algo nuevo, false si encontró existente
- HTTP 201 si fue creado, 200 si ya existía

**`update(UpdateClubCategoryRequest $request, Club $club, ClubCategory $category)` — Actualiza:**
```php
Gate::authorize('update', $category);
$validated = $request->validated();
$category->update($validated);
```

- `Gate::authorize('update', $category)` → verifica permisos (lanza 403 si no). La Policy busca por ClubCategory.
- `$category->update($validated)` → Eloquent solo actualiza los campos presentes en `$validated` (regla `sometimes` del FormRequest)

**`destroy(Club $club, ClubCategory $category)` — Elimina:**
```php
Gate::authorize('delete', $category);
$category->delete();
return response()->noContent();
```

- `response()->noContent()` → HTTP 204 SIN CUERPO (ni `{status, data}`). Es intencional.
- El tipo de retorno es `Response` (NO `JsonResponse`) porque no hay JSON en el body.

---

### 3.2 ClubChannelController

**Archivo:** `app/Http/Controllers/ClubChannelController.php`

**`index(Club $club)` — Lista canales del club:**
```php
$channels = ClubChannel::whereHas('category', fn($q) => $q->where('club_uuid', $club->uuid))
    ->orderBy('sort_order')
    ->cursorPaginate(15);
```

- `whereHas('category', ...)` → busca canales cuya categoría pertenezca al club. Es un subquery EXISTS en SQL.
- Más eficiente que traer todas las categorías del club y luego los canales.

**`store()` — Crea canal con idempotencia:**
```php
$channel = ClubChannel::firstOrCreate(
    ['category_uuid' => $validated['category_uuid'], 'client_uuid' => $validated['client_uuid']],
    [...]
);
```

- La unicidad es por `(category_uuid, client_uuid)` — mismo canal en misma categoría = mismo client_uuid.
- `sort_order` se auto-asigna por categoría (max + 1).

---

### 3.3 ClubRoleController

**Archivo:** `app/Http/Controllers/ClubRoleController.php`

**`index(Club $club)` — Lista roles:**
```php
ClubRole::where('club_uuid', $club->uuid)->orderBy('sort_order')->cursorPaginate(15);
```

**`store()` — Crea rol con idempotencia:**
```php
ClubRole::firstOrCreate(
    ['club_uuid' => $club->uuid, 'client_uuid' => $validated['client_uuid']],
    [
        'name' => $validated['name'],
        'color' => $validated['color'],
        'permissions' => $validated['permissions'] ?? 0,
        'sort_order' => (ClubRole::where('club_uuid', $club->uuid)->max('sort_order') ?? 0) + 1,
        'is_fixed' => false,
    ]
);
```

- `permissions` se valida como integer en el FormRequest (bitmask), el Resource lo descompone en objeto de booleanos.
- `is_fixed => false` por defecto — los roles fijos son del sistema y no se crean desde API.

**`update()` — NO tiene `Gate::authorize()`. Por ahora no hay ClubRolePolicy.** Cuando se cree la Policy, se agrega.

**`destroy()` — Sin Gate por ahora, misma razón.**

---

### 3.4 FriendshipController

**Archivo:** `app/Http/Controllers/FriendshipController.php`

**`index()` — Lista amigos (aceptados):**
```php
$friendships = Friendship::where(function ($q) use ($request) {
    $q->where('sender_uuid', $request->user()->uuid)
      ->orWhere('receiver_uuid', $request->user()->uuid);
})
    ->where('status', 'accepted')
    ->with(['sender', 'receiver'])
    ->orderBy('created_at', 'desc')
    ->cursorPaginate(15);
```

- Busca friendships donde el usuario sea sender o receiver, con estado 'accepted'
- `->with(['sender', 'receiver'])` → carga ambos usuarios para que FriendshipResource pueda determinar quién es "el amigo"
- El Resource usa: si `sender_uuid === currentUser.uuid` → el amigo es `receiver`, sino es `sender`

**`pending()` — Lista solicitudes pendientes:**
```php
Friendship::where('receiver_uuid', $request->user()->uuid)
    ->where('status', 'pending')
    ->with('sender')
    ...
```

- Solo carga `sender` (quien envió la solicitud). El Resource NO entra en la rama de "ambos cargados" y devuelve el formato completo con sender/receiver.

**`store()` — Enviar solicitud:**
```php
$validated = $request->validate([
    'receiver_uuid' => ['required', 'uuid', 'exists:users,uuid'],
]);
```

- Usa `$request->validate()` directo (no FormRequest). Para validaciones simples de 1-2 campos, es aceptable.
- **Idempotencia manual**: busca si ya existe una friendship entre ambos usuarios (en cualquier dirección). Si existe, la devuelve sin crear duplicado.
- Previene auto-solicitud con `if ($request->user()->uuid === $validated['receiver_uuid'])`.

**`respond()` — Aceptar/Rechazar:**
```php
$friendship = Friendship::where('uuid', $requestUuid)
    ->where('receiver_uuid', $request->user()->uuid)
    ->where('status', 'pending')
    ->firstOrFail();
```

- `firstOrFail()` → 404 si no existe, si no es el receiver, o si ya fue respondida.
- El `action` ("accept" o "decline") ya viene validado por `RespondFriendshipRequest`.

---

### 3.5 DmConversationController

**Archivo:** `app/Http/Controllers/DmConversationController.php`

**`index()` — Lista conversaciones DM:**
```php
DmConversation::where('user_one_uuid', $request->user()->uuid)
    ->orWhere('user_two_uuid', $request->user()->uuid)
    ->with(['userOne', 'userTwo', 'lastMessage'])
    ->orderBy('updated_at', 'desc')
    ->cursorPaginate(15);
```

- Filtra donde el usuario sea user_one o user_two
- Carga `lastMessage` (relación `latestOfMany()` que agregamos al modelo)
- Order por `updated_at` descendente: las conversaciones con actividad reciente aparecen primero

**`show()` — Detalle de conversación:**
```php
$dmConversation->load(['userOne', 'userTwo', 'lastMessage']);
```

- En `show()` se usa `->load()` en vez de `->with()` porque el modelo ya viene del Route Model Binding
- El Resource determina quién es el `participant` comparando `user_one_uuid` con el usuario autenticado

**`store()` — Crear/obtener conversación:**
```php
$conversation = DmConversation::where(function ($q) use ($request, $validated) {
    $q->where('user_one_uuid', $request->user()->uuid)
      ->where('user_two_uuid', $validated['recipient_uuid']);
})->orWhere(function ($q) use ($request, $validated) {
    $q->where('user_one_uuid', $validated['recipient_uuid'])
      ->where('user_two_uuid', $request->user()->uuid);
})->first();
```

- Busca en ambos órdenes (user_one/user_two) para evitar duplicados
- Si existe, la devuelve con 200
- Si no, la crea con 201

---

### 3.6 DmMessageController

**Archivo:** `app/Http/Controllers/DmMessageController.php`

**`index()` — Mensajes DM con paginación por cursor:**
```php
DmMessage::where('dm_conversation_uuid', $dmConversation->uuid)
    ->with('sender')
    ->orderBy('created_at', 'desc')
    ->cursorPaginate((int) $request->query('limit', 20));
```

- Mismo patrón que `ChannelMessageController@index`: scroll invertido con `cursorPaginate()` + `orderBy('created_at', 'desc')`
- `limit` viene del query param con default 20

**`store()` — Enviar mensaje DM con idempotencia:**
```php
DmMessage::firstOrCreate(
    ['dm_conversation_uuid' => $dmConversation->uuid, 'client_uuid' => $validated['client_uuid']],
    ['sender_uuid' => $request->user()->uuid, 'content' => $validated['content'], ...]
);
```

- **Idempotencia estricta por DB**: `client_uuid` tiene UNIQUE compuesto con `dm_conversation_uuid`
- Si el frontend retry con el mismo client_uuid, no duplica

---

### 3.7 NotificationController

**Archivo:** `app/Http/Controllers/NotificationController.php`

**`index()` — Lista notificaciones:**
```php
Notification::where('user_uuid', $request->user()->uuid)
    ->orderBy('created_at', 'desc')
    ->cursorPaginate(15);
```

**`markAsRead()` — Marcar como leída:**
```php
$notification->update(['is_read' => true]);
```

- Route Model Binding inyecta `Notification $notification`
- `is_read` es boolean gracias al `cast` en el modelo

---

### 3.8 ExploreController

**Archivo:** `app/Http/Controllers/ExploreController.php`

```php
Club::with('clubOwner')->orderBy('created_at', 'desc')->cursorPaginate(15);
```

- Lista clubs públicos ordenados por creación (los más recientes primero)
- Carga el owner para el Resource
- Por ahora es simple — cuando se agreguen "featured" o "populares", se añade lógica de scoring

---

### 3.9 SearchController

**Archivo:** `app/Http/Controllers/SearchController.php`

```php
$query = $request->query('q');
$filter = $request->query('filter', 'all');
```

- `q` → término de búsqueda
- `filter` → "all" (default), "clubs", o "users"

**Búsqueda de clubs:**
```php
Club::where('name', 'ilike', "%{$query}%")
    ->orWhere('description', 'ilike', "%{$query}%")
    ->take(10)->get();
```

- `ilike` es PostgreSQL case-insensitive
- `take(10)` → límite duro, sin paginación (es búsqueda)

**Búsqueda de usuarios:**
```php
User::where('username', 'ilike', "%{$query}%")
    ->orWhere('user_tag', 'ilike', "%{$query}%")
    ->take(10)->get();
```

**Respuesta:**
```json
{
  "status": "success",
  "data": {
    "clubs": [...],
    "users": [...]
  }
}
```

---

## 4. 🔧 Controllers Existentes que se Tocaron

### 4.1 AuthController — Sin cambios (ya estaba correcto)

Usa Standard Envelope `{status, data}` en register/login/logout.

**Puntos clave de su diseño:**
- `register()`: `Hash::make($validated['password'])` → NUNCA guardar password en texto plano
- `login()`: `$request->authenticate()` → el FormRequest intenta Auth::attempt. Si falla, lanza 422 automático
- `logout()`: `$request->user()->currentAccessToken()->delete()` → elimina SOLO el token actual

### 4.2 UserController — Corregido `updateProfile()`

**Antes:** `unset($validated['current_password'], $validated['new_password'])`
**Después:** Se eliminó el `unset()`

**Los demás métodos están correctos:**
- `me()` → `$request->user()->load('memberships')` para que UserResource tenga `club_uuids`
- `show(User $user)` → Route Model Binding + UserResource
- `sessions()` → `$request->user()->tokens()->orderBy('last_used_at', 'desc')->get()`

### 4.3 ClubController — Sin cambios

**`index()` — patrón interesante:**
```php
$memberships = $request->user()->memberships()
    ->with('club.clubOwner')
    ->orderBy('created_at', 'desc')
    ->cursorPaginate(15);

$clubs = $memberships->map->club;
```

- No consulta clubs directamente, consulta `memberships` (la relación) y extrae el club de cada una
- Mapea con `->map->club` para obtener solo los clubs

### 4.4 ClubMemberController — De show() a store() idempotente

**Eliminado `show()`:** No tenía caso de uso real. `index()` ya devuelve miembros con roles. Si un usuario no aparece en `index()`, no es miembro.

**`store()` ahora usa `firstOrCreate`:** Idempotente. Si el usuario ya es miembro, devuelve 200. Si es nuevo, 201.

### 4.5 ClubMemberRoleController — Reescribito completo

**Antes:** Usaba `$request->validated()` SIN FormRequest en la firma, y creaba en `ClubMemberRole` directamente con campos incorrectos (`user_uuid`, `club_uuid` que no existen en esa tabla pivote).

**Después:**
```php
$member = ClubMember::where('club_uuid', $club->uuid)
    ->where('user_uuid', $user)
    ->firstOrFail();

$member->roles()->syncWithoutDetaching([$validated['role_uuid']]);
```

- Encuentra al ClubMember por club + user (del parámetro de ruta `{user}`)
- Usa `syncWithoutDetaching()` en la relación `roles()` (belongsToMany) — agrega el rol sin desasignar los existentes
- Recibe `AssignClubRoleRequest` correctamente tipado

### 4.6 ChannelMessageController — Sin cambios

Usa `cursorPaginate()` para scroll invertido y `firstOrCreate` para idempotencia. Correcto.

### 4.7 DmConversation — Modelo corregido

**Agregado `lastMessage()`:**
```php
public function lastMessage()
{
    return $this->hasOne(DmMessage::class, 'dm_conversation_uuid', 'uuid')
        ->latestOfMany();
}
```

`latestOfMany()` es un método de Eloquent que hace: `ORDER BY created_at DESC LIMIT 1`. Devuelve el mensaje más reciente de la conversación en una sola subquery eficiente.

---

## 5. 📋 Mapa Completo: Ruta → Controller → Método → Patrón

| Ruta | Controller@method | Patrón | HTTP | Formato |
|------|-------------------|--------|------|---------|
| `POST /auth/register` | AuthController@register | 7 | 201 | `{status, data}` |
| `POST /auth/login` | AuthController@login | 7 | 200 | `{status, data}` |
| `POST /auth/logout` | AuthController@logout | 7 | 200 | `{status, data}` |
| `GET /user` | UserController@me | 1 | 200 | `{status, data}` |
| `PATCH /user` | UserController@updateProfile | 4 | 200 | `{status, data}` |
| `GET /user/sessions` | UserController@sessions | 1 | 200 | `{status, data}` |
| `GET /users/{user}` | UserController@show | 1 | 200 | `{status, data}` |
| `GET /user/friends` | FriendshipController@index | 6 | 200 | `{data, meta}` |
| `GET /user/friend-requests` | FriendshipController@pending | 6 | 200 | `{data, meta}` |
| `POST /user/friend-requests` | FriendshipController@store | 2 | 201 | `{status, data}` |
| `PATCH /user/friend-requests/{uuid}` | FriendshipController@respond | 4 | 200 | `{status, data}` |
| `GET /notifications` | NotificationController@index | 6 | 200 | `{data, meta}` |
| `PATCH /notifications/{n}/read` | NotificationController@markAsRead | 4 | 200 | `{status, data}` |
| `GET /user/clubs` | ClubController@index | 6 | 200 | `{data, meta}` |
| `POST /clubs` | ClubController@store | 3 | 201/200 | `{status, data}` |
| `GET /clubs/{club}` | ClubController@show | 1 | 200 | `{status, data}` |
| `PATCH /clubs/{club}` | ClubController@update | 4 | 200 | `{status, data}` |
| `DELETE /clubs/{club}` | ClubController@destroy | 5 | 204 | sin body |
| `GET /clubs/{club}/members` | ClubMemberController@index | 6 | 200 | `{data, meta}` |
| `POST /clubs/{club}/members` | ClubMemberController@store | 3 | 201/200 | `{status, data}` |
| `DELETE /clubs/{club}/members/{m}` | ClubMemberController@destroy | 5 | 200 | `{status, data}` |
| `GET /clubs/{club}/roles` | ClubRoleController@index | 6 | 200 | `{data, meta}` |
| `POST /clubs/{club}/roles` | ClubRoleController@store | 3 | 201/200 | `{status, data}` |
| `PATCH /clubs/{club}/roles/{r}` | ClubRoleController@update | 4 | 200 | `{status, data}` |
| `DELETE /clubs/{club}/roles/{r}` | ClubRoleController@destroy | 5 | 204 | sin body |
| `POST /clubs/{club}/members/{u}/roles` | ClubMemberRoleController@store | 2 | 201 | `{status, data}` |
| `GET /clubs/{club}/categories` | ClubCategoryController@index | 6 | 200 | `{data, meta}` |
| `POST /clubs/{club}/categories` | ClubCategoryController@store | 3 | 201/200 | `{status, data}` |
| `PATCH /clubs/{club}/categories/{c}` | ClubCategoryController@update | 4 | 200 | `{status, data}` |
| `DELETE /clubs/{club}/categories/{c}` | ClubCategoryController@destroy | 5 | 204 | sin body |
| `GET /clubs/{club}/channels` | ClubChannelController@index | 6 | 200 | `{data, meta}` |
| `POST /clubs/{club}/channels` | ClubChannelController@store | 3 | 201/200 | `{status, data}` |
| `PATCH /clubs/{club}/channels/{c}` | ClubChannelController@update | 4 | 200 | `{status, data}` |
| `DELETE /clubs/{club}/channels/{c}` | ClubChannelController@destroy | 5 | 204 | sin body |
| `GET /channels/{channel}/messages` | ChannelMessageController@index | 6 | 200 | `{data, meta}` |
| `POST /channels/{channel}/messages` | ChannelMessageController@store | 3 | 201/200 | `{status, data}` |
| `GET /user/dm-conversations` | DmConversationController@index | 6 | 200 | `{data, meta}` |
| `GET /dm-conversations/{dm}` | DmConversationController@show | 1 | 200 | `{status, data}` |
| `POST /dm-conversations` | DmConversationController@store | 2 | 201/200 | `{status, data}` |
| `GET /dm-conversations/{dm}/messages` | DmMessageController@index | 6 | 200 | `{data, meta}` |
| `POST /dm-conversations/{dm}/messages` | DmMessageController@store | 3 | 201/200 | `{status, data}` |
| `GET /explore` | ExploreController@index | 6 | 200 | `{data, meta}` |
| `GET /search` | SearchController@index | — | 200 | `{status, data}` |

---

## 6. ❌ Bugs Conocidos que NO se arreglaron

### 6.1 SessionResource — os, browser, ip, location siempre null

La tabla `personal_access_tokens` de Sanctum no tiene columnas para tracking de sesión (OS, browser, IP, ubicación). Para arreglarlo se necesita:

1. Migración que agregue columnas a `personal_access_tokens`:
   ```php
   Schema::table('personal_access_tokens', function (Blueprint $table) {
       $table->string('os')->nullable();
       $table->string('browser')->nullable();
       $table->string('ip')->nullable();
       $table->string('location')->nullable();
   });
   ```
2. En el login, capturar User-Agent e IP:
   ```php
   $token = $user->createToken('auth_token', ['*'], $request->userAgent());
   $token->accessToken->forceFill([
       'ip' => $request->ip(),
       'os' => parse_os_from_useragent($request->userAgent()),
       'browser' => parse_browser_from_useragent($request->userAgent()),
   ])->save();
   ```

### 6.2 ClubMember fillable incompleto

**Archivo:** `app/Models/ClubMember.php`

`#[Fillable(['user_uuid', 'club_uuid'])]` — le faltan `joined_at` y `client_uuid` que sí existen en la migración. Si el frontend envía `client_uuid` al unirse, Laravel lo ignora silenciosamente (mass assignment protection).

### 6.3 ClubController@store — Auto-membresía sin idempotencia

```php
ClubMember::create([
    'user_uuid' => $request->user()->uuid,
    'club_uuid' => $club->uuid,
]);
```

Si el `store()` falla después de crear el club pero antes de crear el ClubMember (raro pero posible con Octane/concurrencia), un retry crearía otro club con distinto `client_uuid`. La auto-membresía no tiene idempotencia porque no usa `firstOrCreate`.

### 6.4 Gate::authorize() sin Policies

Muchos controllers llaman `Gate::authorize()` pero las Policies correspondientes no existen (ClubCategoryPolicy, ClubChannelPolicy, ClubRolePolicy, etc.). Cuando se llame a esos endpoints, Laravel lanzará 403 porque `Gate::authorize('update', $category)` no encuentra una Policy y asume false.

### 6.5 SearchController — SQL Injection potencial

```php
"ilike", "%{$query}%"
```

Eloquent usa parameter binding, así que NO hay SQL injection. Pero `ilike` es PostgreSQL-specific. Si se cambia a MySQL, no funciona.

---

## 7. 🎓 Lecciones Aprendidas

### Lección 1: El Resource NO inventa nombres de columnas ni relaciones

Cada vez que el Resource accede a `$this->algo`, ese `algo` debe ser:
- Una columna real de la tabla del modelo
- O una relación definida en el modelo (`$this->relationName`)
- O un accessor definido en el modelo (`getAlgoAttribute()`)

NO puede ser un nombre inventado. Si el modelo tiene `userOne()`, el Resource usa `$this->userOne`, NO `$this->user1`.

### Lección 2: `firstOrCreate` es el patrón correcto para idempotencia

`firstOrCreate([condición], [valores])`:
- **Condición**: los campos que identifican un registro único (ej: `club_uuid + client_uuid`)
- **Valores**: los campos a insertar SOLO si no existe

NUNCA uses `try/catch` con `QueryException` para manejar UNIQUE violations. Es lento, feo, y escondes errores reales.

### Lección 3: `cursorPaginate()` reemplaza TODO el paginado manual

No necesitas `findOrFail($cursor) + where('created_at', '<', $cursor->created_at)`. `cursorPaginate()` ya hace eso internamente. Funciona también para scroll invertido (chat):
```php
->orderBy('created_at', 'desc')->cursorPaginate(20)
```
Esto trae los 20 más nuevos primero, y el cursor apunta al más viejo de la página.

### Lección 4: El formato de respuesta depende del tipo de operación, no del gusto

| Operación | Formato | Razón |
|-----------|---------|-------|
| Objeto individual | `{status, data}` | El frontend necesita saber si fue exitoso |
| Lista paginada | `{data, meta}` | El frontend necesita next_cursor para infinite query |
| Eliminación | `response()->noContent()` | No hay nada que devolver, 204 ahorra ancho de banda |

NO mezclar: una lista no lleva `status`, un objeto individual no lleva `meta`.

### Lección 5: El modelo NO sabe de HTTP

- ❌ `request()->user()` en el modelo
- ❌ `auth()->id()` en el modelo
- ✅ `$request->user()->uuid` en el Controller
- ✅ Pasar el UUID como parámetro al modelo si es necesario

### Lección 6: Controller no valida, no transforma, no decide

- ❌ `unset($validated['password'])` en el Controller
- ❌ `if ($request->has('role')) { ... }` en el Controller  
- ✅ Validación → FormRequest
- ✅ Transformación → Resource
- ✅ Decisión de negocio → Action/Service (cuando sea necesario)

### Lección 7: `whenLoaded()` vs `whenHas()` en Resources

- `whenLoaded('relation')` → solo incluye el campo si la relación fue eager-loaded. Previene N+1.
- `whenHas('aggregate')` → solo incluye el campo si fue cargado con `withCount()` o `withSum()`.
- `when($condition, $callback)` → incluye el campo solo si `$condition` es true. Útil para lógica condicional.

### Lección 8: La paginación por cursor es obligatoria

`->paginate()` (offset-based) está PROHIBIDO. Razones:
- Offset es O(n) — cuanto más avanzas, más lento
- Cursor es O(log n) — siempre constante gracias a índices
- Si se inserta una fila en la página 1 mientras el usuario está en la página 5, offset se desfasa. Cursor no.

### Lección 9: Los Controllers vacíos documentan su propósito

```php
class ClubRoleController extends Controller
{
    //
}
```

Aunque el controller esté vacío, el docblock explica qué debería hacer cuando se implemente. Esto es mejor que no tener el archivo, porque el route binding `/clubs/{club}/roles/{role}` ya resuelve `ClubRole` automáticamente aunque el método no esté implementado.

### Lección 10: Siempre cargar relaciones ANTES del Resource

```php
// ✅ Bien
$message = ChannelMessage::find($id);
$message->load('sender');
return new MessageResource($message);

// ✅ Mejor (eager loading en la query)
$message = ChannelMessage::with('sender')->find($id);
return new MessageResource($message);

// ❌ Mal (N+1)
$message = ChannelMessage::find($id);
return new MessageResource($message); // El Resource hace otra query para sender
```

El `whenLoaded()` en el Resource es el seguro: si el Controller olvidó cargar la relación, devuelve `null` en vez de 51 queries.
