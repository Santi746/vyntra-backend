# 🧠 Guía: Controllers (Controladores)

**Objetivo:** Crear los Controladores de Vyntra que actúan como el cerebro de cada flujo de la API. Cada Controller recibe la petición ya validada, utiliza los Modelos para interactuar con PostgreSQL, y delega la respuesta a un API Resource.

> [!IMPORTANT]
> **Regla de Oro:** El Controller es el coordinador, NO el obrero. No escribe SQL a mano, no valida datos, no formatea JSON. Solo dirige el tráfico entre capas.

---

## ¿Qué Controllers necesita Vyntra?

Basado en el `api_contract.md`, el `api_requests_manifest.md` y la arquitectura del sistema, estos son los Controllers requeridos:

| Controller | Responsabilidad Principal | Métodos Necesarios |
|---|---|---|
| `AuthController` | Login, Logout, Registro | `register()`, `login()`, `logout()` |
| `UserController` | Perfil del usuario, sesiones activas, amistades | `me()`, `show()`, `updateProfile()`, `sessions()` |
| `ClubController` | CRUD de clubes | `index()`, `show()`, `store()`, `update()`, `destroy()` |
| `ClubMemberController` | Listar miembros, membresías del club, unirse/salir | `index()`, `show()`, `store()`, `destroy()` |
| `ClubMemberRoleController` | Asignar y desasignar roles a miembros del club | `store()` (assignRole) |
| `ClubCategoryController` | CRUD de categorías dentro de un club | `index()`, `store()`, `update()`, `destroy()` |
| `ClubChannelController` | CRUD de canales dentro de una categoría/club | `index()`, `store()`, `update()`, `destroy()` |
| `ClubRoleController` | CRUD de roles dentro del club | `index()`, `store()`, `update()`, `destroy()` |
| `ChannelMessageController` | Enviar y paginar mensajes de canal (cursor) | `index()`, `store()` |
| `DmConversationController` | Crear y listar conversaciones privadas | `index()`, `show()`, `store()` |
| `DmMessageController` | Enviar y paginar mensajes directos (cursor) | `index()`, `store()` |
| `NotificationController` | Listar notificaciones y marcarlas como leídas | `index()`, `markAsRead()` |
| `FriendshipController` | Enviar, listar, aceptar y rechazar solicitudes | `index()` (friends), `pending()` (requests), `respond()` |
| `ExploreController` | Obtener datos para la vista de exploración | `index()` |
| `SearchController` | Búsqueda global de clubes y usuarios | `index()` |

---

## Checklist de Implementación

### 1. Generar los archivos vacíos con Artisan
- [ ] Ejecutar los comandos de generación:
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

### 2. Estructura interna de cada método (Patrón obligatorio)

Todos los métodos de un Controller deben seguir este patrón sin excepción:

```php
public function store(StoreMessageRequest $request, ClubChannel $channel): JsonResponse
{
    // 1. Obtener datos ya validados del Form Request (NO validar aquí)
    $validated = $request->validated();

    // 2. Usar el Modelo para interactuar con la BD
    $message = ChannelMessage::create([
        ...$validated,
        'club_channel_uuid' => $channel->uuid,
        'sender_uuid' => $request->user()->uuid,
    ]);

    // 3. Devolver la respuesta usando un API Resource (NUNCA json() directo)
    return response()->json(
        new MessageResource($message),
        201
    );
}
```

---

### 3. AuthController (Prioridad Alta — se implementa primero)

El `AuthController` es el más crítico porque sin autenticación, ninguna otra ruta funciona.

**Métodos requeridos:**
- [ ] `register(StoreUserRequest $request)` → Crea usuario + devuelve token
- [ ] `login(LoginRequest $request)` → Verifica credenciales + devuelve token
- [ ] `logout(Request $request)` → Revoca el token actual del usuario

**Lógica de login usando Sanctum:**
```php
public function login(LoginRequest $request): JsonResponse
{
    // 1. Delegar la validación e intento de autenticación al Form Request
    $request->authenticate();

    // 2. Obtener el usuario autenticado (si fallara, el Request ya lanzó la excepción)
    $user = Auth::user();

    // 3. Crear un token de Sanctum para este usuario
    $token = $user->createToken('auth_token')->plainTextToken;

    // 4. Devolver el token al frontend
    return response()->json([
        'access_token' => $token,
        'token_type' => 'Bearer',
        'user' => new UserResource($user),
    ]);
}
```

---

### 4. ChannelMessageController (Prioridad Alta — lógica compleja)

Este Controller implementa la paginación por cursor obligatoria definida en las reglas de escalabilidad.

**Métodos requeridos:**
- [ ] `index(ClubChannel $channel, Request $request)` → Lista mensajes con cursor
- [ ] `store(StoreMessageRequest $request, ClubChannel $channel)` → Envía mensaje

**Lógica del cursor en `index`:**
```php
public function index(ClubChannel $channel, Request $request): JsonResponse
{
    $cursor = $request->query('cursor'); // UUID del mensaje más viejo que el usuario tiene en pantalla

    $query = ChannelMessage::where('club_channel_uuid', $channel->uuid)
        ->with('sender') // Carga el usuario remitente en la misma consulta (evita N+1)
        ->orderBy('created_at', 'desc') // Los más nuevos primero (arquitectura invertida)
        ->limit(50);

    // Si hay cursor, continuar desde ese punto
    if ($cursor) {
        $cursorMessage = ChannelMessage::findOrFail($cursor);
        $query->where('created_at', '<', $cursorMessage->created_at);
    }

    $messages = $query->get();

    return response()->json([
        'data' => MessageResource::collection($messages),
        'meta' => [
            'next_cursor' => $messages->count() === 50 ? $messages->last()->uuid : null,
            'per_page' => 50,
        ],
    ]);
}
```

> [!CAUTION]
> Nunca usar `->paginate()` estándar de Laravel para mensajes de chat. Usa siempre la paginación por cursor manual como se muestra arriba. Ver `docs/architecture/database_scalability_rules.md` para entender el por qué.

---

### 5. Listados Estándar y Paginación Infinita (cursorPaginate)

En el frontend (Next.js), existen múltiples interfaces que consumen listas mediante *Scroll Infinito* (`useInfiniteQuery`). Esto requiere **paginación por cursor**. 

Además de los mensajes de chat, los siguientes controladores **deben** implementar paginación por cursor en sus métodos `index()`:
- `DmConversationController` (Lista de chats abiertos)
- `FriendshipController` (Lista de amigos y solicitudes)
- `ClubMemberController` (Lista de miembros de un club)
- `NotificationController` (Notificaciones)
- `SearchController` (Búsqueda global)

A diferencia de los mensajes de chat (que requieren lógica manual invertida), para estas listas estándar **Laravel ofrece el método nativo `cursorPaginate()`**, que simplifica todo el trabajo manteniendo la escalabilidad:

```php
public function index(Club $club): JsonResponse
{
    // cursorPaginate(15) hace toda la magia: lee el ?cursor de la URL y arma la consulta
    $members = $club->members()->cursorPaginate(15);

    return response()->json([
        'data' => ClubMemberResource::collection($members->items()),
        'meta' => [
            'next_cursor' => $members->nextCursor()?->encode(), // Laravel formatea el cursor
            'per_page'    => $members->perPage(),
        ],
    ]);
}
```

---

### 6. Reglas generales de todos los Controllers

- [ ] Nunca escribir SQL en bruto (`DB::select("SELECT...")`) dentro de un Controller
- [ ] Nunca usar `response()->json(['data' => $model->toArray()])` — usar siempre API Resources
- [ ] Usar siempre inyección de dependencias en los parámetros del método (Route Model Binding)
- [ ] Manejar errores con `try/catch` solo cuando el error es recuperable (ej: transacciones)
- [ ] Retornar el código HTTP correcto: `200` lectura, `201` creación, `204` eliminación sin body

---

## Resultado esperado al finalizar

```
✅ 15 Controllers creados en app/Http/Controllers/
✅ AuthController funcional con login/register/logout
✅ ChannelMessageController y DmMessageController con paginación por cursor manual
✅ Todos los métodos siguen el patrón: Request → Model → Resource
```

---

## Siguiente paso
➡️ [05_routes_guide.md](./05_routes_guide.md)
