# 🧠 Guía: Controllers (Controladores) ✅

> **Completado.** 16 Controllers creados siguiendo el patrón coordinador + FormRequest + Resource.

**Objetivo:** Crear los Controladores de Vyntra que actúan como el coordinador de cada flujo de la API. Cada Controller recibe la petición ya validada, utiliza los Modelos para interactuar con PostgreSQL, y delega la respuesta a un API Resource.

> [!IMPORTANT]
> **Regla de Oro:** El Controller es el coordinador, NO el obrero. No escribe SQL a mano, no valida datos, no formatea JSON, no hace broadcast() síncrono. Solo dirige el tráfico entre capas.

> **REGLA 3** de `docs/architecture/IMPORTANT_PRACTICES.md`: El Controller se limita a validar → autorizar → ejecutar DB → dispatch(Event/Job) → cargar relaciones → responder Resource. Prohibido broadcast() síncrono.

---

## Los 7 Patrones de un Controller

No existe "un patrón único". Hay **7 formas distintas** de escribir un método, y cada cual se usa en una situación específica. Esta sección explica CUÁNDO usar cada uno y POR QUÉ la sintaxis cambia.

---

### Patrón 1 — Lectura de un solo objeto (`show`, `me`)

**Cuándo:** El frontend pide UN objeto por su UUID (o el usuario autenticado).

```php
// show() — con Route Model Binding, Laravel busca el modelo automáticamente
public function show(Club $club): JsonResponse
{
    $club->load('clubOwner'); // cargar relaciones anidadas antes del Resource

    return response()->json([
        'status' => 'success',
        'data' => new ClubResource($club),
    ]);
}

// me() — devuelve el usuario autenticado, sin parámetro de ruta
public function me(Request $request): JsonResponse
{
    return response()->json([
        'status' => 'success',
        'data' => new UserResource($request->user()->load('memberships')),
    ]);
}
```

**Sintaxis clave:**
- `new ClubResource($club)` → objeto individual (NO `::collection`)
- Se devuelve dentro de `['status' => 'success', 'data' => ...]` (Standard Envelope para objetos)
- Route Model Binding: Laravel inyecta `Club $club` Finds the registro por UUID automáticamente, si no existe lanza 404
- `$club->load(...)` carga relaciones ANTES del Resource, evitando N+1

---

### Patrón 2 — Creación simple (`store` sin idempotencia)

**Cuándo:** Crear un recurso donde NO hay riesgo de duplicado (ej: unirse a un club — cada usuario solo se une una vez).

```php
public function store(Request $request, Club $club): JsonResponse
{
    $membership = ClubMember::create([
        'user_uuid' => $request->user()->uuid,
        'club_uuid' => $club->uuid,
    ]);

    return response()->json([
        'status' => 'success',
        'data' => new ClubMemberResource($membership->load('user')),
    ], 201);
}
```

**Sintaxis clave:**
- Código HTTP `201` (Created) en el segundo parámetro de `response()->json`
- `->load('user')` DESPUÉS de create → carga la relación recién creada
- Si la BD lanza UNIQUE violation porque ya existe, Laravel devuelve 500. Para evitarlo y responder 200 si ya existe → usar Patrón 3

---

### Patrón 3 — Creación idempotente (`store` con `firstOrCreate`)

**Cuándo:** Crear un recurso donde el frontend puede reintentar si hay fallo de red (ej: enviar mensaje, crear club). El `client_uuid` garantiza que si se envía la misma petición dos veces, no se duplica.

```php
public function store(StoreChannelMessageRequest $request, ClubChannel $channel): JsonResponse
{
    $validated = $request->validated();

    $message = ChannelMessage::firstOrCreate(
        [   // CONDICIÓN: si ya existe un registro con estos campos, lo devuelve
            'club_channel_uuid' => $channel->uuid,
            'client_uuid' => $validated['client_uuid'],
        ],
        [   // VALORES: si NO existe, crea uno nuevo con estos campos
            'sender_uuid' => $request->user()->uuid,
            'content' => $validated['content'],
            'parent_message_uuid' => $validated['parent_message_uuid'] ?? null,
        ]
    );

    $message->load('sender');

    // Despachar evento para WebSockets (futuro Reverb/Horizon).
    // NUNCA hacer broadcast() síncrono aquí — delegar a ShouldBroadcast + ShouldQueue.
    // if ($message->wasRecentlyCreated) {
    //     MessageSentEvent::dispatch($message);
    // }

    // Si el mensaje ya existía → 200. Si es nuevo → 201
    $status = $message->wasRecentlyCreated ? 201 : 200;

    return response()->json([
        'status' => 'success',
        'data' => new MessageResource($message),
    ], $status);
}
```

**Sintaxis clave:**
- `firstOrCreate([condición], [valores])` → Laravel busca primero. Si encuentra, lo devuelve. Si no, lo crea con los valores.
- `$message->wasRecentlyCreated` → boolean que dice si `firstOrCreate` acaba de crear el registro o lo encontró existente
- `$status = $message->wasRecentlyCreated ? 201 : 200` → 201 si es nuevo, 200 si ya existía
- Los campos que forman parte de la condición de unicidad (como `client_uuid`) van en el **primer array**, NO en los valores
- **REGLA 3**: Después de DB write y antes de Resource, dispatchar un Event/Job. Nunca `broadcast()` síncrono dentro del Controller.

---

### Patrón 4 — Actualización (`update`)

**Cuándo:** Modificar campos de un recurso existente.

```php
public function update(UpdateClubRequest $request, Club $club): JsonResponse
{
    Gate::authorize('update', $club); // verificar permisos antes de tocar la BD

    $validated = $request->validated(); // solo los campos que envió el frontend
    $club->update($validated);          // Eloquent solo actualiza los campos presentes
    $club->load('clubOwner');           // recargar relaciones para el Resource

    return response()->json([
        'status' => 'success',
        'data' => new ClubResource($club),
    ]);
}
```

**Sintaxis clave:**
- `Gate::authorize('update', $club)` → lanza 403 si el usuario no tiene permisos. SIEMPRE antes de modificar datos
- `$request->validated()` con Form Requests que usan regla `sometimes` → solo devuelve los campos que el frontend envió, los ausentes se ignoran
- `$club->update($validated)` → Eloquent solo actualiza los campos presentes en `$validated`, no toca los demás
- Código HTTP 200 (por defecto) — NO 200 explícito porque es el valor por defecto

---

### Patrón 5 — Eliminación (`destroy`)

**Cuándo:** Eliminar un recurso.

```php
public function destroy(Club $club): Response
{
    Gate::authorize('delete', $club);
    $club->delete(); // soft delete si el modelo usa SoftDeletes

    return response()->noContent(); // 204 sin body
}
```

**Sintaxis clave:**
- Tipo de retorno `Response` (NO `JsonResponse`) — porque no hay body
- `response()->noContent()` → devuelve HTTP 204 sin cuerpo de respuesta
- `$club->delete()` → si el modelo tiene `SoftDeletes`, hace soft delete. Si no, elimina definitivamente
- `Gate::authorize()` siempre antes de la acción destructiva

---

### Patrón 6 — Lista paginada por cursor (`index` con scroll infinito)

**Cuándo:** El frontend consume listas con scroll infinito (`useInfiniteQuery`). Necesita paginación por cursor, NO offset.

```php
public function index(Request $request, Club $club): JsonResponse
{
    $members = ClubMember::where('club_uuid', $club->uuid)
        ->with(['user', 'roles'])           // siempre cargar relaciones aquí
        ->orderBy('created_at', 'asc')      // orden estable para el cursor
        ->cursorPaginate(15);               // Laravel lee ?cursor de la URL automáticamente

    return response()->json([
        'data' => ClubMemberResource::collection($members->items()),
        'meta' => [
            'next_cursor' => $members->nextCursor()?->encode(),
            'per_page'    => $members->perPage(),
        ],
    ]);
}
```

**Sintaxis clave:**
- `cursorPaginate(15)` → Laravel hace toda la magia: lee `?cursor` de la URL, genera la consulta WHERE, y calcula el siguiente cursor automáticamente
- `$members->items()` → devuelve solo los elementos de la página actual (array plano)
- `$members->nextCursor()?->encode()` → siguiente cursor para el frontend, `null` si no hay más páginas. El `?->` es null-safe operator: si nextCursor devuelve null, no llama encode() y devuelve null
- `$members->perPage()` → devuelve el tamaño de página configurado
- `Resource::collection($members->items())` → para arrays, NO `new Resource()` que es para objeto individual
- La respuesta usa `{data, meta}` (SIN `status`), eso es intencional — las listas paginadas tienen su propio formato

**ATENCIÓN: `cursorPaginate()`**

`cursorPaginate()` funciona para listas normales (miembros, notificaciones, etc.). Para mensajes de chat con scroll invertido (los más nuevos arriba), TAMBIÉN funciona — solo hay que agregar `orderBy('created_at', 'desc')` y Laravel hace el WHERE automáticamente:

```php
// Mensajes de chat — mismo patrón, solo cambia el orderBy
$messages = ChannelMessage::where('club_channel_uuid', $channel->uuid)
    ->with('sender')
    ->orderBy('created_at', 'desc')
    ->cursorPaginate($limit);
```

---

### Patrón 7 — Autenticación (`login`, `register`, `logout`)

**Cuándo:** Solo para AuthController. Estos métodos NO siguen los patrones anteriores porque manejan tokens, no recursos CRUD.

```php
// register: crear usuario + devolver token
public function register(StoreUserRequest $request): JsonResponse
{
    $validated = $request->validated();

    $user = User::create([
        'username'   => $validated['username'],
        'first_name' => $validated['first_name'],
        'last_name'  => $validated['last_name'],
        'email'      => $validated['email'],
        'password'   => Hash::make($validated['password']),
    ]);

    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'status' => 'success',
        'data' => [
            'user' => new UserResource($user),
            'token' => $token,
        ],
    ], 201);
}

// login: verificar credenciales + devolver token
public function login(LoginRequest $request): JsonResponse
{
    $request->authenticate(); // el FormRequest verifica email+password

    /** @var \App\Models\User $user */
    $user = $request->user();
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'status' => 'success',
        'data' => [
            'user' => new UserResource($user),
            'token' => $token,
        ],
    ], 200);
}

// logout: revocar el token actual
public function logout(Request $request): JsonResponse
{
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'status' => 'success',
        'data' => null,
    ], 200);
}
```

**Sintaxis clave:**
- `$request->authenticate()` → método especial del LoginRequest que intenta Auth::attempt. Si falla, lanza 422 AUTOMÁTICAMENTE y el controller no se ejecuta
- `Hash::make()` → hashea la contraseña antes de guardar (NUNCA guardar passwords en texto plano)
- `$user->createToken('auth_token')->plainTextToken` → Sanctum crea el token y lo devuelve en texto plano (solo se ve una vez)
- `$request->user()->currentAccessToken()->delete()` → elimina SOLO el token actual, NO todos los del usuario
- `data` puede ser `null` (logout) o contener múltiples campos (login/register)

---

## Mapa rápido: ¿Qué patrón usa cada método?

| Si tu método... | Patrón | Código HTTP | Tipo de respuesta |
|---|---|---|---|
| Devuelve un objeto por UUID | 1 (show) | 200 | `{status, data}` |
| Devuelve el usuario autenticado | 1 (me) | 200 | `{status, data}` |
| Crea un recurso simple (sin riesgo duplicado) | 2 (store simple) | 201 | `{status, data}` |
| Crea un recurso con idempotencia (client_uuid) | 3 (firstOrCreate) | 201 o 200 | `{status, data}` |
| Modifica un recurso existente | 4 (update) | 200 | `{status, data}` |
| Elimina un recurso | 5 (destroy) | 204 | sin body |
| Lista con paginación por cursor | 6 (cursorPaginate) | 200 | `{data, meta}` |
| Login / Register / Logout | 7 (auth) | 200 / 201 | `{status, data}` |

---

## ¿Qué Controllers necesita Vyntra?

| Controller | Responsabilidad Principal | Métodos Necesarios |
|---|---|---|
| `AuthController` | Login, Logout, Registro | `register()` (7), `login()` (7), `logout()` (7) |
| `UserController` | Perfil del usuario, sesiones | `me()` (1), `show()` (1), `updateProfile()` (4), `sessions()` (1) |
| `ClubController` | CRUD de clubes | `index()` (6), `show()` (1), `store()` (3), `update()` (4), `destroy()` (5) |
| `ClubMemberController` | Miembros de club | `index()` (6), `store()` (2), `destroy()` (5) |
| `ClubMemberRoleController` | Asignar roles | `store()` (2) |
| `ClubCategoryController` | CRUD categorías | `index()` (6), `store()` (3), `update()` (4), `destroy()` (5) |
| `ClubChannelController` | CRUD canales | `index()` (6), `store()` (3), `update()` (4), `destroy()` (5) |
| `ClubRoleController` | CRUD roles | `index()` (6), `store()` (3), `update()` (4), `destroy()` (5) |
| `ChannelMessageController` | Mensajes de canal | `index()` (6), `store()` (3) |
| `DmConversationController` | Conversaciones DM | `index()` (6), `show()` (1), `store()` (3) |
| `DmMessageController` | Mensajes DM | `index()` (6), `store()` (3) |
| `NotificationController` | Notificaciones | `index()` (6), `markAsRead()` (4) |
| `FriendshipController` | Amistades | `index()` (6), `pending()` (6), `respond()` (4) |
| `ExploreController` | Vista de exploración | `index()` (6) |
| `SearchController` | Búsqueda global | `index()` (6) |

> Los números entre paréntesis son los patrones de esta guía. Ej: `store()` (3) = Patrón 3 (firstOrCreate).

---

## Checklist de Implementación

### 1. Generar los archivos vacíos con Artisan
- [ ] Ejecutar los comandos:
  ```bash
  php artisan make:controller AuthController
  php artisan make:controller UserController
  php artisan make:controller ClubController
  php artisan make:controller ClubMemberController
  php artisan make:controller ClubMemberRoleController
  php artisan make:controller ClubCategoryController
  php artisan make:controller ClubChannelController
  php artisan make:controller ClubRoleController
  php artisan make:controller ChannelMessageController
  php artisan make:controller DmConversationController
  php artisan make:controller DmMessageController
  php artisan make:controller NotificationController
  php artisan make:controller FriendshipController
  php artisan make:controller ExploreController
  php artisan make:controller SearchController
  ```
  > Los archivos se crearán en `app/Http/Controllers/`

---

### 2. Firmas de método y parámetros

Cada método recibe parámetros diferentes según lo que necesita. Aquí están las firmas correctas:

```php
// Métodos que leen datos (GET) — usan Request para query params
public function index(Request $request, Club $club): JsonResponse
public function me(Request $request): JsonResponse

// Métodos que reciben datos por UUID en la URL — Route Model Binding
public function show(Club $club): JsonResponse

// Métodos que modifican datos (POST/PUT/PATCH) — usan FormRequest
public function store(StoreClubRequest $request): JsonResponse
public function store(StoreChannelMessageRequest $request, ClubChannel $channel): JsonResponse
public function update(UpdateClubRequest $request, Club $club): JsonResponse

// Métodos que eliminan — solo necesitan el modelo
public function destroy(Club $club): Response  // NOTA: Response, no JsonResponse

// Métodos de autenticación — FormRequest especial
public function login(LoginRequest $request): JsonResponse
public function logout(Request $request): JsonResponse
```

**Nota sobre tipos de retorno:**
- `JsonResponse` → para todos los métodos que devuelven JSON
- `Response` → solo para `destroy()` que devuelve `response()->noContent()` (204 sin body)

---

### 3. Formato de respuesta: Standard Envelope

Todas las respuestas exitosas usan uno de estos dos formatos:

**Objeto individual (show, store, update, me, login, register, logout):**
```php
return response()->json([
    'status' => 'success',
    'data' => new ClubResource($club),
], 201);
```

**Lista paginada (index):**
```php
return response()->json([
    'data' => ClubMemberResource::collection($members->items()),
    'meta' => [
        'next_cursor' => $members->nextCursor()?->encode(),
        'per_page'    => $members->perPage(),
    ],
]);
```

**Eliminación (destroy):**
```php
return response()->noContent(); // 204 sin body
```

> La diferencia es intencional: los objetos llevan `status` + `data`, las listas llevan `data` + `meta`. No mezclar.

---

### 4. Reglas generales

- [ ] Nunca escribir SQL en bruto (`DB::select("SELECT...")`) dentro de un Controller
- [ ] Nunca usar `response()->json(['data' => $model->toArray()])` — usar siempre API Resources
- [ ] Usar siempre Route Model Binding en los parámetros (`Club $club` en vez de `$id`)
- [ ] Usar `Gate::authorize()` antes de modificar o eliminar datos
- [ ] Cargar relaciones con `->with()` o `->load()` ANTES de pasar al Resource
- [ ] Después de DB write y antes de Resource: `dispatch(new XxxEvent(...))` o `XxxJob::dispatch()`
- [ ] **Prohibido** `broadcast()` síncrono dentro del Controller — delegar a Event + ShouldQueue
- [ ] Retornar el código HTTP correcto: `200` lectura/actualización, `201` creación, `204` eliminación sin body
- [ ] Para idempotencia: usar `firstOrCreate()` con `client_uuid`, NO `try/catch` con `QueryException`
- [ ] Para paginación: usar `cursorPaginate()`, NO `paginate()` estándar

---

## Resultado esperado al finalizar

```
✅ 15 Controllers creados en app/Http/Controllers/
✅ AuthController funcional con login/register/logout
✅ Todos los métodos usan el patrón correcto según la tabla de arriba
✅ Todas las respuestas siguen Standard Envelope
✅ cursorPaginate() en todas las listas, paginate() en NINGUNA
```

---

## Siguiente paso
➡️ [05_middleware_policies_guide.md](./05_middleware_policies_guide.md)