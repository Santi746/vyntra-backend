# Lección Final — Paso 05: Middleware + Policies + Bitwise + Preview + Tests

> Lo que se hizo, qué se solucionó, qué debes aprender como desarrollador.

---

## 1. RESUMEN EJECUTIVO

Este paso consolidó la arquitectura de autorización de Vyntra. Se partía de un sistema con policies vacías, controllers sin `Gate::authorize`, sistema bitwise huérfano, tests rotos y documentación desalineada. Se cerró cada hueco siguiendo prácticas senior: Policies dedicadas por modelo, anti-privilege-escalation por bit value, filtrado de recursos privados en backend, endpoint preview público, y suite de tests HTTP robusta.

**Resultado de tests:** 76/76 tests pasan en los archivos tocados. 0 regresiones introducidas.

---

## 2. ARCHIVOS CREADOS (2)

| Archivo | Propósito |
|---|---|
| `app/Policies/ClubRolePolicy.php` | Policy dedicada con helper `outranks()` que previene privilege-escalation. Métodos `create`, `update`, `delete`. |
| (Documentación interna) | `docs/project_steps_guides/api_layer_guides/lessons/05_middleware_policies_lesson.md` (anterior) |

---

## 3. ARCHIVOS MODIFICADOS (17)

### Backend Laravel

| Archivo | Cambio |
|---|---|
| `app/Providers/AppServiceProvider.php` | Registrado `ClubRolePolicy`. 9 policies totales registradas. |
| `app/Http/Controllers/ClubRoleController.php` | `store/update/destroy` ahora usan `Gate::authorize('create'/'update'/'delete', [ClubRole::class, $club, $role])`. |
| `app/Http/Controllers/ClubController.php` | Añadido `preview()` (única excepción sin membresía). `show()` filtra `is_private` con regla owner/ADMINISTRATOR ven todo, demás necesitan VIEW_CHANNELS. Helper privado `canSeePrivateChannels()`. |
| `app/Http/Controllers/ClubCategoryController.php` | `index()` con `Gate::authorize('viewAny', ...)` + filtro de privados. `store()` con `Gate::authorize('create', ...)`. |
| `app/Http/Controllers/ClubChannelController.php` | `index()` con `Gate::authorize('viewAny', ...)` + filtro de privados. `store()` con `Gate::authorize('create', ...)`. |
| `app/Policies/ClubCategoryPolicy.php` | Añadidos `viewAny($user, Club $club)` y `create($user, Club $club)`. |
| `app/Policies/ClubChannelPolicy.php` | Añadidos `viewAny($user, Club $club)` y `create($user, Club $club)`. |
| `routes/api.php` | Nueva ruta `GET /api/clubs/{club}/preview` (dentro de `auth:sanctum`, sin Gate en el controller). |
| `tests/Feature/NotificationControllerTest.php` | **Reescrito** con patrón HTTP puro (`actingAs sanctum` + `getJson/patchJson`). 7 tests. |
| `tests/Feature/DmConversationControllerTest.php` | **Reescrito**. 10 tests. |
| `tests/Feature/DmMessageControllerTest.php` | **Reescrito**. 8 tests. |
| `tests/Feature/ChannelMessageControllerTest.php` | **Reescrito**. 7 tests con setup completo de Club/Category/Channel. |

### Documentación

| Archivo | Cambio |
|---|---|
| `docs/api_contract.md` | Sección "Permisos bitwise" añadida con tabla de 16 bits y referencia cruzada a `ClubPermission` enum. `permissions` documentado como integer (bitmask) en vez de objeto. |
| `docs/api_requests_manifest.md` | ROLE-02 y ROLE-03 actualizados (`permissions: "required\|integer\|min:0"`). CLUB-02b (preview) añadido. CLUB-02 anotado con "Requiere Membresía". |

### Frontend

| Archivo | Cambio |
|---|---|
| `src/services/club.service.js` | Añadido `getClubPreview(club_uuid)` con JSDoc explicando la excepción arquitectónica. |
| `src/features/clubs/components/atoms/ClubChannel.jsx` | Acepta prop `is_private` y renderiza `<Lock />` de lucide-react. |
| `src/features/clubs/components/molecules/ClubCategory.jsx` | Renderiza `<Lock />` cuando `is_private=true`. Pasa `is_private` a `ClubChannel`. |

---

## 4. QUÉ SE SOLUCIONÓ

### 4.1 Seguridad

| # | Problema | Solución |
|---|---|---|
| 1 | `ClubRoleController@store` no tenía Gate — cualquier autenticado podía crear roles en cualquier club | `Gate::authorize('create', [ClubRole::class, $club])` con `MANAGE_ROLES` |
| 2 | `ClubRoleController@update/destroy` usaban `Gate::authorize('update', $club)` (ClubPolicy semánticamente incorrecto) | Reemplazado por `Gate::authorize('update'/'delete', [ClubRole::class, $club, $role])` |
| 3 | Admin con `MANAGE_ROLES` podía borrar/editar un rol con `MANAGE_CLUB` o superior (privilege-escalation) | Helper `outranks()` que compara bitmasks: el target no puede tener bits que el actor no tenga |
| 4 | Roles `is_fixed=true` (sistema) podían ser borrados por cualquier admin | Solo el owner puede borrar roles `is_fixed` |
| 5 | `ClubCategoryController@index` y `ClubChannelController@index` permitían listar categorías/canales de cualquier club sin ser miembro | Añadido `Gate::authorize('viewAny', ...)` |
| 6 | Canales y categorías con `is_private=true` se mostraban a todos los miembros | Filtrado en backend: solo owner/ADMINISTRATOR/rol con VIEW_CHANNELS ve privados |
| 7 | `ClubCategoryController@store` y `ClubChannelController@store` no requerían `MANAGE_CHANNELS` | Añadido `Gate::authorize('create', ...)` |

### 4.2 Arquitectura

| # | Problema | Solución |
|---|---|---|
| 8 | Política de Club usaba `update` para gestionar roles (semánticamente incorrecto) | Creada `ClubRolePolicy` dedicada |
| 9 | `is_private` se persistía pero el frontend no lo usaba para nada (ghost field) | Lock icon añadido a `ClubChannel.jsx` y `ClubCategory.jsx` |
| 10 | No había forma de previsualizar un club siendo no-miembro | Endpoint `GET /api/clubs/{club}/preview` con auth pero sin membresía |
| 11 | `api_contract.md` y `api_requests_manifest.md` desactualizados | Docs alineadas con la realidad del código (integer bitmask) |

### 4.3 Calidad de tests

| # | Problema | Solución |
|---|---|---|
| 12 | 4 archivos de tests usaban patrón de llamada directa al controller (`new Controller(); $controller->method()`) que bypasea middleware, FormRequest resolution y validación | Reescritos con patrón HTTP puro: `actingAs sanctum` + `getJson/postJson/patchJson/deleteJson` + `assertStatus(401/403/404/422)` |

---

## 5. LO QUE NO SE CAMBIÓ (decisiones explícitas)

| Elemento | Por qué se preservó |
|---|---|
| `ClubPolicy::delete` → owner-only | Eliminar un club es demasiado destructivo para delegar en roles |
| `ClubPolicy::view` → membresía | Cualquier miembro puede ver el club; no requiere permiso extra |
| `ChannelMessagePolicy::viewAny` → solo membresía | La separación privado/público se hace en el controller, no en la policy (mantiene semántica simple) |
| `ClubRoleResource` → raw integer | Frontend ya opera con `BigInt()` correctamente |
| `api_contract.md` → solo docs, sin cambiar Resource | El código es la verdad; los docs se alinearon al código |
| 5 archivos de tests pre-existentes rotos (Friendship, Explore, Search, User, Debug) | No estaban en el scope; se dejarán para un paso posterior |

---

## 6. CONCEPTOS QUE DEBES APRENDER (LECCIÓN)

### 6.1 Policy dedicada vs Policy genérica reutilizada

**❌ Anti-patrón (antes):**
```php
// ClubRoleController
Gate::authorize('update', $club);  // ¿Esto es "actualizar el club" o "actualizar un rol"?
```

**✅ Buena práctica (ahora):**
```php
Gate::authorize('update', [ClubRole::class, $club, $role]);
// La Policy recibe los 3 elementos: el modelo, el padre, y el target específico
```

**Por qué:** Una Policy por modelo permite que cada una tenga su propia semántica. `ClubPolicy::update` significa "actualizar este club", `ClubRolePolicy::update` significa "actualizar este rol en este club". Son preguntas diferentes.

### 6.2 Anti-privilege-escalation por bit value

```php
// El usuario solo puede editar/borrar roles cuyos permisos no excedan los suyos
return ($userBitmask & $target->permissions) === $target->permissions;
```

**Por qué:** Si un admin tiene `MANAGE_ROLES` (bit 2) y un rol tiene `MANAGE_CLUB` (bit 3) + `MANAGE_ROLES`, ese rol tiene bits que el admin no tiene. No debería poder editarlo ni borrarlo, porque podría darse permisos que no le corresponden.

**Analogía:** Un店长 (manager de tienda) puede editar las ventas del día, pero no puede editar la contabilidad de la empresa. No tiene los "bits" para hacerlo.

### 6.3 Filtrado de recursos privados en backend, no frontend

**❌ Anti-patrón:**
- Backend devuelve todos los canales (públicos + privados)
- Frontend filtra según `useCheckPermission(VIEW_CHANNELS)`
- **Problema:** alguien con `curl` ve los nombres de canales privados en la respuesta JSON

**✅ Buena práctica:**
- Backend filtra según el permiso del usuario
- Frontend solo confía en lo que recibe
- **Beneficio:** ni siquiera un attacker que bypasea el frontend puede ver canales privados

### 6.4 Endpoint preview: la excepción justificada

La regla general en Vyntra es: **cualquier endpoint de club requiere membresía**. Pero hay UNA excepción justificada: el preview del club (banner, nombre, descripción, members_count) debe ser visible para que un usuario no miembro pueda evaluar si quiere unirse.

**Cómo se implementa la excepción:**
- Está dentro de `auth:sanctum` (para saber quién pregunta y devolver `is_member`)
- NO tiene `Gate::authorize` (porque no requiere membresía)
- Devuelve un campo extra `is_member` para que el frontend sepa si debe mostrar "Unirse" o "Entrar al club"

### 6.5 Tests HTTP vs tests unitarios de controller

**❌ Anti-patrón (lo que estaba mal):**
```php
$controller = new ClubController();
$response = $controller->show($club);  // bypasea TODO
```

**Problemas:**
- No pasa por el middleware `auth:sanctum` (no hay user)
- No resuelve el FormRequest via container
- No ejecuta validación
- No dispara el route model binding
- El exception handler global no se ejecuta

**✅ Buena práctica (lo que se hizo):**
```php
$this->actingAs($user, 'sanctum');  // Simula que el user está autenticado
$response = $this->getJson('/api/clubs/' . $club->uuid);
$response->assertStatus(200);  // 401 si no auth, 403 si sin permiso, 200 si OK
```

**Cuándo usar cada uno:**
- **Tests HTTP** (`getJson`, `postJson`): Para verificar comportamiento end-to-end del controller.
- **Tests unitarios del controller** (llamar al método directamente): Solo cuando quieras testear lógica pura que NO depende de auth/validación/middleware (raro).

### 6.6 Eager loading con select específico

```php
->with(['roles' => fn ($q) => $q->select('club_roles.uuid', 'club_roles.permissions')])
```

**Por qué importa:** Sin el `select()`, Eloquent carga TODAS las columnas de la tabla. Con el `select()`, solo cargas lo que necesitas. Esto es importante cuando la tabla tiene muchas columnas o BLOBs.

### 6.7 Documentación alineada con el código

La regla de oro: **si el código dice A y el doc dice B, alguien va a implementar lo que dice B y se va a romper.** Por eso actualizamos `api_contract.md` y `api_requests_manifest.md` para reflejar que `permissions` es un integer (bitmask), no un objeto de booleanos. Si el frontend lee la doc y espera objeto, el código lo desilusiona.

### 6.8 El `Lock` icon como semántica visual

El campo `is_private` viajaba backend↔frontend pero el frontend lo descartaba. Era un "ghost field". El Lock icon de lucide-react es la convención visual estándar (Discord, Slack, Teams) para indicar que algo es privado. Una sola palabra vale más que un boolean: "este canal es privado".

---

## 7. FLUJO COMPLETO AHORA (POST-IMPLEMENTACIÓN)

### 7.1 Un usuario no miembro ve una card de club

```
Frontend:  ClubCard click → router.push('?preview=<uuid>')
Frontend:  ClubPreviewModal monta → useClubPreview (mock por ahora)
Backend:   GET /api/clubs/{club}/preview
           → auth:sanctum (sabe quién pregunta)
           → NO Gate (cualquier autenticado puede ver)
           → Devuelve { name, banner, ..., is_member: false }
Frontend:  Renderiza el modal con "Unirse al club" button
```

### 7.2 Un usuario miembro entra a un club

```
Frontend:  Click en club (donde es miembro) → /clubs/<uuid>
Frontend:  useClub(uuid) → GET /api/clubs/{uuid}
Backend:   auth:sanctum (user autenticado)
           Gate::authorize('view', $club) → ¿es miembro? sí → 200
           Filtra categorías: ¿es owner o tiene VIEW_CHANNELS? sí → ve privados
           Filtra canales: igual
Frontend:  Renderiza la estructura del club con canales
```

### 7.3 Un moderador crea una categoría

```
Frontend:  useCheckPermission(club_uuid, MANAGE_CHANNELS) → true (mostrar botón)
Frontend:  Click "Crear categoría" → POST /api/clubs/{club}/categories
Backend:   auth:sanctum (user autenticado)
           Gate::authorize('create', [ClubCategory::class, $club])
           → $user->hasClubPermission($club, MANAGE_CHANNELS) → true → 201
Frontend:  Optimistic update → categoría aparece
```

### 7.4 Un admin intenta borrar un rol superior

```
Frontend:  Settings → Roles → Click "Borrar" en un rol con MANAGE_CLUB
Frontend:  DELETE /api/clubs/{club}/roles/{role}
Backend:   Gate::authorize('delete', [ClubRole::class, $club, $role])
           → is_fixed? No
           → outranks($user, $club, $role)?
             → owner? No
             → has MANAGE_ROLES? Sí
             → userBitmask & role.permissions === role.permissions?
               → No (role tiene MANAGE_CLUB que user no tiene)
             → return false
           → 403 Forbidden
Frontend:  Toast "No tienes permisos para borrar este rol"
```

---

## 8. CÓMO PROBAR

### Tests automáticos

```bash
# Todos los tests de policies y controllers tocados
php artisan test --filter="ClubRole|ClubController|ClubCategoryController|ClubChannelController|ChannelMessageController|NotificationController|DmMessageController|DmConversationController"
# Resultado: 76/76 PASS
```

### Manual con Insomnia/Postman

```http
# 1. Login (ya existente)
POST /api/auth/login

# 2. Ver preview de club sin ser miembro (debería funcionar)
GET /api/clubs/{club_uuid}/preview
Authorization: Bearer {token}
# → 200 con { is_member: false, ... }

# 3. Ver detalle del club (debería fallar si no es miembro)
GET /api/clubs/{club_uuid}
# → 403 Forbidden

# 4. Listar categorías de club del que no es miembro
GET /api/clubs/{club_uuid}/categories
# → 403 Forbidden

# 5. Intentar crear categoría sin MANAGE_CHANNELS
POST /api/clubs/{club_uuid}/categories
# → 403 Forbidden

# 6. Crear categoría con MANAGE_CHANNELS
POST /api/clubs/{club_uuid}/categories
Authorization: Bearer {token}
# → 201 Created

# 7. Listar canales con filtrado de privados
GET /api/clubs/{club_uuid}/channels
# Como miembro SIN VIEW_CHANNELS, no ve canales privados en la respuesta
# Como owner o con VIEW_CHANNELS, los ve

# 8. Borrar un rol superior (anti-escalation)
DELETE /api/clubs/{club_uuid}/roles/{role_uuid}
# Como admin con MANAGE_ROLES pero sin MANAGE_CLUB, no puede borrar
# roles con MANAGE_CLUB
# → 403 Forbidden
```

---

## 9. ARCHIVOS PENDIENTES (deuda técnica documentada)

| Archivo | Por qué no se tocó | Cuándo hacerlo |
|---|---|---|
| `tests/Feature/FriendshipControllerTest.php` | Mismo patrón roto (llamada directa). Fuera de scope de este paso. | Paso dedicado a reescritura de tests de friendships |
| `tests/Feature/SearchControllerTest.php` | Mismo patrón. TypeError con FormRequest. | Paso dedicado |
| `tests/Feature/UserControllerTest.php` | Tests con `null` user. | Paso dedicado |
| `tests/Feature/ExploreControllerTest.php` | "Undefined array key status" — ExploreController no devuelve `status` en su respuesta. Inconsistencia. | Cuando se decida la respuesta de ExploreController |
| `tests/Feature/DebugExploreTest.php` | Test de debug. Borrable. | Cleanup |
| Caché de `User::hasClubPermission` con Redis | El método hace 1 query cada vez. En endpoints con muchos checks, podría ser N+1. | Cuando se identifique cuello de botella en performance |
| `MANAGE_CATEGORIES` separado de `MANAGE_CHANNELS` | Discord, Slack y Teams los mantienen juntos. YAGNI. | Si en el futuro se quiere granularidad fina |
| Validación de `is_private` para `ChannelMessagePolicy::viewAny` | Pre-existente, no es scope. | Decidir UX: ¿canales privados ocultan mensajes? |

---

## 10. RESUMEN DE IMPLEMENTACIÓN

| Fase | Trabajo | Archivos | Tests añadidos/cambiados |
|---|---|---|---|
| A | Cerrar `MANAGE_ROLES` con anti-privilege-escalation | 3 (1 Policy nueva, 1 Controller, 1 Provider) | 0 (existentes pasan) |
| B | Preview + filtrado de is_private | 6 (1 método nuevo, 5 mod) | 0 |
| C | Documentación alineada | 2 (api_contract + manifest) | 0 |
| D | Tests reescritos con HTTP puro | 4 tests reescritos | 32 tests OK |
| E | Frontend preview + Lock icons | 3 (1 service, 2 componentes) | — |

**Total: 17 archivos modificados, 2 creados, 76/76 tests pasan.**
