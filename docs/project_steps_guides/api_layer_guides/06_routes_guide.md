# 🚪 Guía: Rutas API (routes/api.php)

**Objetivo:** Documentar todos los endpoints de Vyntra registrados en `routes/api.php`. Este archivo conecta cada URL con su Controller y aplica los middlewares de seguridad.

> [!IMPORTANT]
> **Regla de Oro:** Las rutas son solo declaraciones de URLs. No contienen lógica. Nunca escribas lógica de negocio, consultas a la BD ni validaciones dentro de `routes/api.php`. Su única responsabilidad es el mapeo URL → Controller.

---

## Estructura de rutas en Vyntra

Las rutas se dividen en dos grandes grupos:

### Grupo 1: Rutas Públicas (Sin autenticación)
Solo el registro y el login. Cualquier persona puede acceder.

### Grupo 2: Rutas Protegidas (Requieren token de Sanctum)
Todo lo demás. Si el frontend no envía el token `Bearer` en la cabecera `Authorization`, Laravel devuelve automáticamente un error `401 Unauthorized`.

---

## Rutas registradas actualmente

### Rutas públicas de autenticación
```php
// ============================================================
// RUTAS PÚBLICAS (No requieren autenticación)
// ============================================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);
});
```

---

### Rutas protegidas

```php
// ============================================================
// RUTAS PROTEGIDAS (Requieren token Bearer de Sanctum)
// ============================================================
Route::middleware('auth:sanctum')->group(function () {

    // ============================================================
    // RUTAS DE LECTURA (GET) — Sin rate limiting
    // ============================================================

    // -- Usuario --
    Route::get('/user',                [UserController::class, 'me']);
    Route::get('/user/sessions',       [UserController::class, 'sessions']);
    Route::get('/users/{user}',        [UserController::class, 'show']);

    // -- Amistades --
    Route::get('/user/friends',              [FriendshipController::class, 'index']);
    Route::get('/user/friend-requests',      [FriendshipController::class, 'pending']);

    // -- Notificaciones --
    Route::get('/notifications',                    [NotificationController::class, 'index']);

    // -- Clubes --
    Route::get('/user/clubs',      [ClubController::class, 'index']);
    Route::get('/clubs/{club}/preview', [ClubController::class, 'preview']);
    Route::get('/clubs/{club}',    [ClubController::class, 'show']);

    // -- Miembros --
    Route::get('/clubs/{club}/members', [ClubMemberController::class, 'index']);

    // -- Roles --
    Route::get('/clubs/{club}/roles',   [ClubRoleController::class, 'index']);

    // -- Categorías --
    Route::get('/clubs/{club}/categories', [ClubCategoryController::class, 'index']);

    // -- Canales --
    Route::get('/clubs/{club}/channels', [ClubChannelController::class, 'index']);

    // -- Mensajes --
    Route::get('/channels/{channel}/messages', [ChannelMessageController::class, 'index']);

    // -- DM --
    Route::get('/user/dm-conversations',                      [DmConversationController::class, 'index']);
    Route::get('/dm-conversations/{dm_conversation}',          [DmConversationController::class, 'show']);
    Route::get('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'index']);

    // -- Dashboard y Búsqueda --
    Route::get('/explore', [ExploreController::class, 'index']);
    Route::get('/search',  [SearchController::class, 'index']);

    // ============================================================
    // RUTAS DE ESCRITURA (POST/PATCH/DELETE) — Rate limited
    // ============================================================
    Route::middleware('throttle:10,1')->group(function () {

        // -- Autenticación --
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // -- Usuario --
        Route::patch('/user', [UserController::class, 'updateProfile']);

        // -- Amistades --
        Route::post('/user/friend-requests',                  [FriendshipController::class, 'store']);
        Route::patch('/user/friend-requests/{request_uuid}', [FriendshipController::class, 'respond']);

        // -- Notificaciones --
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

        // -- Clubes --
        Route::post('/clubs',           [ClubController::class, 'store']);
        Route::patch('/clubs/{club}',   [ClubController::class, 'update']);
        Route::delete('/clubs/{club}',  [ClubController::class, 'destroy']);

        // -- Miembros --
        Route::post('/clubs/{club}/members',            [ClubMemberController::class, 'store']);
        Route::delete('/clubs/{club}/members/{member}', [ClubMemberController::class, 'destroy']);

        // -- Roles --
        Route::post('/clubs/{club}/roles',                    [ClubRoleController::class, 'store']);
        Route::patch('/clubs/{club}/roles',                   [ClubRoleController::class, 'update']);
        Route::delete('/clubs/{club}/roles/{role}',           [ClubRoleController::class, 'destroy']);
        Route::post('/clubs/{club}/members/{user}/roles',     [ClubMemberRoleController::class, 'store']);

        // -- Categorías --
        Route::post('/clubs/{club}/categories',               [ClubCategoryController::class, 'store']);
        Route::patch('/clubs/{club}/categories',              [ClubCategoryController::class, 'update']);
        Route::delete('/clubs/{club}/categories/{category}',  [ClubCategoryController::class, 'destroy']);

        // -- Canales --
        Route::post('/clubs/{club}/channels',             [ClubChannelController::class, 'store']);
        Route::patch('/clubs/{club}/channels/{channel}',  [ClubChannelController::class, 'update']);
        Route::delete('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'destroy']);

        // -- Mensajes --
        Route::post('/channels/{channel}/messages', [ChannelMessageController::class, 'store']);

        // -- DM --
        Route::post('/dm-conversations',                          [DmConversationController::class, 'store']);
        Route::post('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'store']);
    });
});
```

---

## Verificación

Para confirmar que todas las rutas están registradas correctamente:

```bash
php artisan route:list --path=api
```

Debe mostrar una tabla con las **44 rutas**, su método HTTP, URL, middleware y Controller asignado.

---

## Probar las rutas (Insomnia / Postman)

### 1. Registro de usuario
```
POST http://localhost:8000/api/auth/register
Content-Type: application/json

{
  "username": "tester",
  "first_name": "Test",
  "last_name": "User",
  "email": "test@example.com",
  "password": "password",
  "password_confirmation": "password"
}
```
- ✅ `201 Created` con `access_token` en el JSON
- ❌ `422` si falta un campo obligatorio

### 2. Inicio de sesión
```
POST http://localhost:8000/api/auth/login
Content-Type: application/json

{
  "email": "test@example.com",
  "password": "password"
}
```
- ✅ `200 OK` con `access_token`

### 3. Ruta protegida (usando el token)
```
GET http://localhost:8000/api/user
Authorization: Bearer {access_token}
```
- ✅ `200 OK` con datos del usuario
- ❌ `401 Unauthorized` si no se envía el token

---

## Resultado esperado

```
✅ 44 rutas registradas en routes/api.php
✅ Públicas y protegidas bien separadas
✅ Todas conectadas a sus Controllers
✅ php artisan route:list sin errores
✅ Sanctum funciona (401 sin token, 200 con token)
```

---

## Rate Limiting (throttle)

> **REGLA 2** de `docs/architecture/IMPORTANT_PRACTICES.md`.

Las rutas de escritura (POST/PATCH/DELETE) están agrupadas bajo un middleware `throttle:10,1` que limita a **10 requests por minuto** por IP. Esto protege la futura infraestructura de Redis/Reverb del spam o abusos.

**Estructura de middlewares:**
```
auth:sanctum (GET routes) → sin throttle
auth:sanctum + throttle:10,1 (POST/PATCH/DELETE routes) → rate limited
```

Las rutas de lectura (GET) no tienen rate limiting para evitar falsos positivos en carga de páginas. Si en el futuro se requiere, se puede ajustar el threshold por ruta específica.

---

## Siguiente paso (Futuro)
➡️ WebSockets con Laravel Reverb (implementación del tiempo real)
