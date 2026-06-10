# Lecciones de Arquitectura — Primera Auditoría y Correcciones

**Fecha:** 30/05/2026  
**Objetivo:** Corregir desviaciones detectadas en la capa de Controllers/Resources contra `real_time_rules.md` y `api_contract.md`.

---

## 🔴 Lección 1: No asumas columnas que no existen en la BD

### Archivo modificado
- `app/Http/Resources/SessionResource.php` — **Reescrito completamente**
- `app/Http/Controllers/UserController.php` — **Líneas 91-98 corregidas**

### ¿Qué había?
`SessionResource` intentaba devolver `os`, `browser`, `ip`, `location`, `is_current`, `type` — columnas que **no existen** en la tabla `personal_access_tokens` de Sanctum. Esa tabla solo tiene: `id`, `name`, `token`, `abilities`, `last_used_at`, `expires_at`, `created_at`, `updated_at`.

Además, `UserController::sessions()` **no usaba el Resource**. Hacía:
```php
$tokens = $request->user()->tokens()
    ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at']);
return response()->json(['data' => $tokens]);
```
Devolvía los **modelos crudos** al frontend — violando la regla de "siempre usar API Resources" del `http_request_lifecycle.md`.

### ¿Qué se hizo?
1. `SessionResource` ahora mapea SOLO las columnas reales: `id`, `name`, `abilities`, `last_used_at`, `created_at`.
2. `UserController::sessions()` ahora usa `SessionResource::collection($tokens)`.

### Lección para el futuro
**Siempre verifica el schema real de la BD antes de escribir un Resource.** Si usas `php artisan migrate` primero y luego inspeccionas la tabla con tu gestor de BD (o `\Schema::getColumnListing()`), evitas inventar columnas fantasma. Un Resource que referencia columnas inexistentes **no falla en desarrollo si nunca se llama**, pero explota en producción cuando algún frontend lo invoca.

---

## 🔴 Lección 2: Siempre usa `$request->user()`, nunca `auth()` en Controllers

### Archivo modificado
- `app/Http/Controllers/ClubController.php` — **Línea 150 corregida**

### ¿Qué había?
```php
// destroy() - línea 150
if ($club->owner_uuid !== auth()->id()) {

// update() - línea 122 (ya estaba bien)
if ($club->owner_uuid !== $request->user()->uuid) {
```

Inconsistencia: en `update()` usabas `$request->user()`, en `destroy()` usabas `auth()`. El mismo archivo, dos métodos, dos formas distintas de obtener el usuario.

### ¿Qué se hizo?
```php
// destroy() - corregido
if ($club->owner_uuid !== $request->user()->uuid) {
```

### Lección para el futuro
En una app tradicional con PHP-FPM, `auth()` funciona. **Pero con Laravel Octane el mundo cambia.** Octane mantiene la app viva en RAM entre peticiones. Si usas `auth()` dentro de un controller, bajo ciertos escenarios de workers concurrentes, podrías leer el usuario de una petición anterior.

**Regla de oro con Octane:** Siempre inyecta `Request $request` en los parámetros del método y usa `$request->user()`. `auth()` solo es seguro en Service Providers o middleware antes de que la request esté disponible.

Además: nunca mezcles estilos dentro del mismo archivo. Define una convención y aplícala en **todos** los métodos.

### Bonus
`ClubController::index()` también usaba `auth()->user()` y **no recibía `Request $request`** como parámetro. Se corrigió para recibir `Request $request` y usar `$request->user()`.

---

## 🔴 Lección 3: HTTP 204 NO puede tener body

### Archivo modificado
- `app/Http/Controllers/ClubController.php` — **Línea 161 corregida**

### ¿Qué había?
```php
return response()->json([], 204);
```

HTTP 204 (No Content) es un código de estado que indica "la operación se completó exitosamente pero no hay contenido para devolver". **Por definición del protocolo HTTP, un 204 no debe tener body.** `response()->json([], 204)` genera un body `{}` — técnicamente inválido.

### ¿Qué se hizo?
```php
return response()->noContent();
```

### Lección para el futuro
Conóce los códigos HTTP como la palma de tu mano:
- **200** — Lectura/actualización exitosa con body
- **201** — Creación exitosa con body
- **204** — Eliminación/actualización exitosa **sin body**
- **400** — Error del cliente
- **401** — No autenticado
- **403** — No autorizado (sin permisos)
- **404** — Recurso no encontrado
- **422** — Error de validación

Usar `response()->noContent()` es más semántico y liviano que forzar un `json` vacío.

---

## 🟡 Lección 4: Define y respeta un Standard Envelope desde el Día 1

### Archivos modificados
- `app/Http/Controllers/ClubController.php` — `show()`, `store()`, `update()`
- `app/Http/Controllers/ChannelMessageController.php` — `store()`
- `app/Http/Controllers/UserController.php` — `me()`, `show()`, `updateProfile()`
- `app/Http/Resources/ClubResource.php` — se agregó `categories`

### ¿Qué había?
El `api_contract.md` define dos formatos de respuesta:

**Objeto único:**
```json
{
  "status": "success",
  "data": { ... }
}
```

**Listado paginado:**
```json
{
  "data": [ ... ],
  "meta": { "next_cursor": "...", "per_page": 50 }
}
```

Pero en los controllers, algunos métodos devolvían el Resource "pelado":
```php
return response()->json(new ClubResource($club));
```

Y otros lo envolvían parcialmente:
```php
return response()->json(['data' => ClubResource::collection($clubs)]);
```

**El resultado:** el frontend no sabe qué estructura esperar. A veces recibe `{ data: ... }`, a veces recibe `{ uuid, name, ... }` directamente.

### ¿Qué se hizo?
Todos los métodos que devuelven un **objeto único** ahora usan:
```php
return response()->json([
    'status' => 'success',
    'data' => new XXXResource($resource),
], 201); // o 200 según corresponda
```

Los **listados paginados** (con `meta`) mantienen `{ data: [...], meta: {...} }` — sin `status` porque el meta ya describe el resultado.

### Lección para el futuro
**El contrato de API no es opcional.** No importa si "queda más limpio" devolver el Resource directo — si el contrato dice `{ status, data }`, todos los endpoints de objeto único deben cumplirlo. El frontend no debería tener que preguntarse "¿este endpoint me devuelve envuelto o pelado?".

Esto es especialmente importante cuando implementes **Reverb**: los eventos WebSocket deben emitir el mismo formato JSON que la API REST. Si los payloads de REST y WebSocket no coinciden, React Query duplicará mensajes o el frontend tendrá que escribir dos parsers distintos.

### Extra: `ClubResource` no exponía `categories`
`ClubController::show()` cargaba `categories.channels` con eager loading, pero `ClubResource` no tenía el campo `categories`. El frontend hacía una petición que devolvía datos incompletos. Se agregó:
```php
"categories" => ClubCategoryResource::collection($this->whenLoaded('categories')),
```

---

## 📋 Resumen de Archivos Modificados

| Archivo | Cambio | Tipo |
|---------|--------|------|
| `app/Http/Resources/SessionResource.php` | Reescribir con columnas reales de Sanctum | 🔴 Bug |
| `app/Http/Controllers/UserController.php` | Usar SessionResource en sessions() | 🔴 Bug |
| `app/Http/Controllers/ClubController.php` | `auth()->id()` → `$request->user()->uuid` | 🔴 Octane |
| `app/Http/Controllers/ClubController.php` | `response()->json([], 204)` → `response()->noContent()` | 🔴 HTTP |
| `app/Http/Controllers/ClubController.php` | `index()` recibe `Request` y usa `$request->user()` | 🟡 Octane |
| `app/Http/Controllers/ClubController.php` | Envolvente `{ status, data }` en show/store/update | 🟡 Contrato |
| `app/Http/Controllers/ChannelMessageController.php` | Envolvente `{ status, data }` en store | 🟡 Contrato |
| `app/Http/Controllers/UserController.php` | Envolvente `{ status, data }` en me/show/updateProfile | 🟡 Contrato |
| `app/Http/Resources/ClubResource.php` | Agregar `categories` con `whenLoaded` | 🟡 Datos faltantes |

---

## 🧠 Principios para Llevarte

1. **La BD es la fuente de verdad.** No inventes columnas en Resources. Siempre verifica el schema.
2. **`$request->user()` siempre, `auth()` nunca** en controllers. Octane te lo agradecerá.
3. **HTTP 204 no tiene body.** Punto. Usa `response()->noContent()`.
4. **El contrato de API es ley.** Define el envelope al inicio y nunca te desvíes.
5. **Cada capa hace UNA cosa.** Controller coordina, Resource formatea, Request valida. Si un método hace dos cosas, refactoriza.
6. **Sé consistente.** Si usas `$request->user()->uuid` en un método, úsalo en todos. Si envuelves en `{ status, data }` en un endpoint, hazlo en todos.
