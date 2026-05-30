# 🚪 Guía: Rutas API (routes/api.php)

**Objetivo:** Registrar todos los endpoints de Vyntra en el archivo `routes/api.php`, conectando cada URL con su Controller correspondiente y aplicando los middlewares de seguridad correctos.

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

## Checklist de Implementación

### 1. Verificar que `routes/api.php` existe
- [ ] Confirmar que el archivo fue creado por `php artisan install:api` (Paso 01)

---

### 2. Registrar las rutas públicas de autenticación
- [ ] Añadir al inicio de `routes/api.php`:
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

### 3. Registrar las rutas protegidas
- [ ] Añadir a continuación en `routes/api.php`:
  ```php
  // ============================================================
  // RUTAS PROTEGIDAS (Requieren token Bearer de Sanctum)
  // ============================================================
  Route::middleware('auth:sanctum')->group(function () {

      // -- Autenticación --
      Route::post('/auth/logout', [AuthController::class, 'logout']);

      // -- Usuario Autenticado y Perfiles --
      Route::get('/user',                [UserController::class, 'me']);
      Route::patch('/user',              [UserController::class, 'updateProfile']);
      Route::get('/user/sessions',       [UserController::class, 'sessions']);
      Route::get('/users/{user_uuid}',   [UserController::class, 'show']); // Perfil público de otro usuario

      // -- Amistades (Amigos y Solicitudes) --
      Route::get('/user/friends',        [FriendshipController::class, 'index']);
      Route::get('/user/friend-requests', [FriendshipController::class, 'pending']);
      Route::patch('/user/friend-requests/{request_uuid}', [FriendshipController::class, 'respond']);

      // -- Notificaciones --
      Route::get('/notifications',              [NotificationController::class, 'index']);
      Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

      // -- Clubes --
      Route::get('/user/clubs',     [ClubController::class, 'index']); // Clubes del usuario autenticado
      Route::post('/clubs',         [ClubController::class, 'store']);
      Route::get('/clubs/{club}',   [ClubController::class, 'show']);
      Route::patch('/clubs/{club}', [ClubController::class, 'update']);
      Route::delete('/clubs/{club}',[ClubController::class, 'destroy']);

      // -- Miembros y Roles del Club --
      Route::get('/clubs/{club}/members',          [ClubMemberController::class, 'index']);
      Route::post('/clubs/{club}/members',         [ClubMemberController::class, 'store']);
      Route::get('/clubs/{club}/members/{member}', [ClubMemberController::class, 'show']);
      Route::delete('/clubs/{club}/members/{member}', [ClubMemberController::class, 'destroy']);
      Route::get('/clubs/{club}/bans',             [ClubMemberController::class, 'bans']); // Listado de baneados
      
      // Roles del Club (Rutas CRUD y asignación)
      Route::get('/clubs/{club}/roles',            [ClubRoleController::class, 'index']);
      Route::post('/clubs/{club}/roles',           [ClubRoleController::class, 'store']);
      Route::patch('/clubs/{club}/roles/{role}',   [ClubRoleController::class, 'update']);
      Route::delete('/clubs/{club}/roles/{role}',  [ClubRoleController::class, 'destroy']);
      Route::post('/clubs/{club}/members/{member}/roles', [ClubMemberRoleController::class, 'store']); // Asignar Rol

      // -- Categorías del Club --
      Route::get('/clubs/{club}/categories',            [ClubCategoryController::class, 'index']);
      Route::post('/clubs/{club}/categories',           [ClubCategoryController::class, 'store']);
      Route::patch('/clubs/{club}/categories/{category}', [ClubCategoryController::class, 'update']);
      Route::delete('/clubs/{club}/categories/{category}',[ClubCategoryController::class, 'destroy']);

      // -- Canales del Club (Canales creados en la raíz del club o asociados a categorías) --
      Route::get('/clubs/{club}/channels',          [ClubChannelController::class, 'index']);
      Route::post('/clubs/{club}/channels',         [ClubChannelController::class, 'store']);
      Route::patch('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'update']);
      Route::delete('/clubs/{club}/channels/{channel}',[ClubChannelController::class, 'destroy']);

      // -- Mensajes de Canal (paginación por cursor obligatoria) --
      Route::get('/channels/{channel}/messages',  [ChannelMessageController::class, 'index']);
      Route::post('/channels/{channel}/messages', [ChannelMessageController::class, 'store']);

      // -- Conversaciones de Mensajes Directos (DM) --
      Route::get('/user/dm-conversations',                    [DmConversationController::class, 'index']);
      Route::get('/dm-conversations/{dm_conversation}',        [DmConversationController::class, 'show']);
      Route::get('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'index']);
      Route::post('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'store']);

      // -- Dashboard y Búsqueda --
      Route::get('/explore', [ExploreController::class, 'index']);
      Route::get('/search',  [SearchController::class, 'index']);
  });
  ```

---

### 4. Importar todos los Controllers al inicio del archivo
- [ ] Añadir todos los `use` al inicio de `routes/api.php`:
  ```php
  use App\Http\Controllers\AuthController;
  use App\Http\Controllers\UserController;
  use App\Http\Controllers\ClubController;
  use App\Http\Controllers\ClubMemberController;
  use App\Http\Controllers\ClubMemberRoleController;
  use App\Http\Controllers\ClubCategoryController;
  use App\Http\Controllers\ClubChannelController;
  use App\Http\Controllers\ClubRoleController;
  use App\Http\Controllers\ChannelMessageController;
  use App\Http\Controllers\DmConversationController;
  use App\Http\Controllers\DmMessageController;
  use App\Http\Controllers\NotificationController;
  use App\Http\Controllers\FriendshipController;
  use App\Http\Controllers\ExploreController;
  use App\Http\Controllers\SearchController;
  ```

---

### 5. Verificar que todas las rutas se registraron correctamente
- [ ] Ejecutar el siguiente comando y revisar que todas las rutas aparecen:
  ```bash
  php artisan route:list --path=api
  ```
  Deberías ver una tabla con todas las rutas registradas, su método HTTP, URL, middleware y Controller asignado.

---

### 6. Probar las rutas públicas en Insomnia
- [ ] Abrir Insomnia y hacer una petición de registro:
  ```
  POST http://localhost:8000/api/auth/register
  Content-Type: application/json

  {
    "username": "tester",
    "user_tag": "tester-1337",
    "first_name": "Test",
    "last_name": "User",
    "email": "test@example.com",
    "password": "password",
    "password_confirmation": "password"
  }
  ```
  - ✅ Respuesta esperada: `201 Created` con `access_token` en el JSON
  - ❌ Respuesta de error esperada: `422` si falta un campo obligatorio

- [ ] Hacer una petición de login con las mismas credenciales:
  ```
  POST http://localhost:8000/api/auth/login
  Content-Type: application/json

  {
    "email": "test@example.com",
    "password": "password"
  }
  ```
  - ✅ Respuesta esperada: `200 OK` con `access_token`

- [ ] Copiar el `access_token` y hacer una petición protegida:
  ```
  GET http://localhost:8000/api/user
  Authorization: Bearer {el_token_que_copiaste}
  ```
  - ✅ Respuesta esperada: `200 OK` con los datos del usuario autenticado

---

## Resultado esperado al finalizar

```
✅ routes/api.php con rutas públicas y protegidas bien separadas
✅ Todas las rutas conectadas a sus Controllers correspondientes
✅ php artisan route:list --path=api muestra todas las rutas sin errores
✅ Register y Login funcionan correctamente en Insomnia
✅ Las rutas protegidas devuelven 401 sin token y 200 con token válido
```

---

## Siguiente paso (Futuro)
➡️ WebSockets con Laravel Reverb (implementación del tiempo real)
