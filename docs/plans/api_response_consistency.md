# Plan: Unificar Formato de Respuestas API

> **Fecha:** 2026-07-04
> **Autor:** Asistente Ejecutor
> **Estado:** Pendiente de aprobación

---

## 1. Diagnóstico: La Inconsistencia

Actualmente los controllers del proyecto responden con **3 formatos distintos** para operaciones similares:

### Formato A: Listados (`index`) — SIN `status`

```php
// ClubController@index, ClubRoleController@index,
// ClubChannelController@index, ClubMemberController@index,
// FriendshipController@index, FriendshipController@pending
return response()->json([
    'data' => ClubResource::collection($items),
    'meta' => [
        'next_cursor' => ...,
        'per_page' => 15,
    ],
]);
```

### Formato B: Single/Create/Update — CON `status`

```php
// ClubController@show, ClubController@store, ClubController@update,
// ClubRoleController@store, ClubRoleController@update,
// ClubChannelController@store, ClubChannelController@update,
// ClubMemberController@store, FriendshipController@store, etc.
return response()->json([
    'status' => 'success',
    'data' => new ClubResource($model),
], 200);
```

### Formato C: Auth — Array manual

```php
// AuthController@register, AuthController@login
return response()->json([
    'status' => 'success',
    'data' => [
        'user' => new UserResource($user),
        'token' => $token,
    ],
], 201);
```

### Formato D: Excepciones — Formato híbrido

```php
// ClubController@preview — bypassea el Resource con array_merge + toArray()
return response()->json([
    'status' => 'success',
    'data' => array_merge(
        (new ClubResource($club))->toArray(request()),
        ['is_member' => $isMember],
    ),
]);
```

---

## 2. ¿Por Qué Es una Chapuza?

### 2.1 Contrato API implícito

No hay un **archivo central** que defina "así es como respondemos". El frontend tiene que adaptarse a 3 formatos diferentes:

```typescript
// Auth necesita esto:
interface AuthResponse {
    status: 'success';
    data: { user: User; token: string } | null;
}

// Club index necesita esto otro:
interface ClubListResponse {
    data: Club[];
    meta: { next_cursor: string | null; per_page: number };
}

// Club show necesita esto:
interface ClubShowResponse {
    status: 'success';
    data: Club;
}
```

Para el frontend, cada endpoint es un caso especial en lugar de "todos siguen la misma regla".

### 2.2 DRY violado

Si mañana queremos:
- Agregar `timestamp` a todas las respuestas
- Cambiar `status: 'success'` por `success: true`
- Agregar un campo `request_id` para debugging

Hay que editar **cada método de cada controller** (20+ ubicaciones). No hay un solo lugar donde se defina la forma de responder.

### 2.3 Onboarding difícil

Un desarrollador nuevo que llega al proyecto mira `ClubController::index` y escribe su nuevo controller igual. Pero después ve `ClubMemberController::store` que usa otro formato. ¿Cuál es el correcto? No hay manera de saberlo sin leer toda la codebase.

### 2.4 Testing frágil

Los tests tienen que verificar 3 formatos diferentes:

```php
// Test para index
$response->assertJsonStructure(['data', 'meta' => ['next_cursor', 'per_page']]);

// Test para show
$response->assertJsonStructure(['status', 'data']);
```

Cada test es único. No se puede reutilizar lógica de aserción.

---

## 3. ¿Por Qué Debería Solucionarse?

| Razón | Impacto |
|-------|---------|
| **Contrato API predecible** | Frontend sabe que `{status, data}` SIEMPRE aplica |
| **Un solo punto de cambio** | Modificar el formato global toca 1 archivo, no 20 |
| **Menos código en controllers** | `$this->success($data)` vs 6 líneas de array |
| **Tests más simples** | Un trait de testing reusable para formato |
| **Documentación viva** | El trait ES la documentación del formato de respuesta |
| **Escalabilidad a 100k req/s** | Menos código = menos oportunidades de bug |

---

## 4. La Solución: Un Trait `ApiResponse`

### 4.1 ¿Qué es un Trait en PHP?

Un trait es un **mecanismo de reutilización de código** en PHP. Una clase puede "importar" múltiples traits, y los métodos del trait se comportan como si hubieran sido escritos directamente en la clase.

```php
trait ApiResponse {
    public function success(mixed $data, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], $code);
    }
}

// Cualquier controller puede usarlo:
class ClubController extends Controller {
    use ApiResponse;  // ← aquí importa todos los métodos del trait

    public function show(Club $club): JsonResponse
    {
        Gate::authorize('view', $club);
        return $this->success(new ClubResource($club));
        //       ^^^^^^^^ método del trait
    }
}
```

### 4.2 Métodos que Tendría el Trait

| Método | Respuesta | Para qué |
|--------|-----------|----------|
| `success($data, $code=200)` | `{status: 'success', data: $data}` | show, store, update |
| `created($data)` | `{status: 'success', data: $data}` con 201 | store (nuevo) |
| `collection($items, $meta)` | `{data: [...], meta: {...}}` | index (listados) |
| `noContent()` | `204 No Content` | destroy |
| `error($message, $code=422)` | `{status: 'error', message: $message}` | errores |

### 4.3 Código completo del trait

```php
<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\CursorPaginator;

trait ApiResponse
{
    /**
     * Respuesta exitosa para un recurso singular.
     * Se usa en: show, store, update, login, register, etc.
     */
    protected function success(JsonResource|array|null $data, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ], $code);
    }

    /**
     * Respuesta para creación (201).
     */
    protected function created(JsonResource|array $data): JsonResponse
    {
        return $this->success($data, 201);
    }

    /**
     * Respuesta para listados paginados por cursor.
     * Sigue el formato definido en docs/api_contract.md.
     */
    protected function collection(ResourceCollection $collection, ?CursorPaginator $paginator = null): JsonResponse
    {
        $response = [
            'data' => $collection,
        ];

        if ($paginator) {
            $response['meta'] = [
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'per_page' => $paginator->perPage(),
            ];
        }

        return response()->json($response);
    }

    /**
     * Respuesta para eliminación (204 sin cuerpo).
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Respuesta de error.
     */
    protected function error(string $message, int $code = 422, mixed $errors = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
```

### 4.4 Cómo quedaría un controller después

```php
class ClubController extends Controller
{
    use ApiResponse;  // ← esto es todo lo que se agrega

    public function index(Request $request): JsonResponse
    {
        $memberships = $request->user()->memberships()
            ->with('club.clubOwner')
            ->orderBy('created_at', 'desc')
            ->cursorPaginate(15);

        return $this->collection(
            ClubResource::collection($memberships->map->club),
            $memberships,
        );
        // Antes: 8 líneas manuales
        // Ahora: 2 líneas
    }

    public function show(Club $club): JsonResponse
    {
        Gate::authorize('view', $club);
        // ... lógica ...
        return $this->success(new ClubResource($club));
        // Antes: 6 líneas manuales
        // Ahora: 1 línea
    }

    public function store(StoreClubRequest $request): JsonResponse
    {
        // ... lógica ...
        $wasCreated = $club->wasRecentlyCreated;
        return $wasCreated
            ? $this->created(new ClubResource($club))
            : $this->success(new ClubResource($club));
    }

    public function destroy(Club $club): JsonResponse
    {
        Gate::authorize('delete', $club);
        $club->delete();
        return $this->noContent();
    }
}
```

### 4.5 ¿Por qué un trait y no una clase base?

| Opción | Ventaja | Desventaja |
|--------|---------|------------|
| **Trait** | Funciona con cualquier clase base. Se puede usar en `Controller`, `Notification`, etc. | Colisión de nombres (poco probable) |
| **Clase base** `ApiController` | Herencia simple | Si el controller ya extiende otra clase, no funciona |
| **Helper estático** `Api::success()` | No necesita importar nada en la clase | No es sobreescribible por controller |

Se elige **trait** porque:
- PHP permite múltiples traits, una sola herencia
- Los controllers ya extienden `Controller` — no podemos cambiarlos a otra clase base
- Es el patrón que ya usa Laravel internamente (`AuthorizesRequests`, `ValidatesRequests`, etc.)

---

## 5. Plan de Implementación

### 5.1 Archivos a crear

| Archivo | Acción |
|---------|--------|
| `app/Http/Responses/ApiResponse.php` | Crear trait con métodos success, created, collection, noContent, error |

### 5.2 Archivos a modificar

| Archivo | Cambio |
|---------|--------|
| `app/Http/Controllers/Controller.php` | Agregar `use ApiResponse;` (todos heredan el trait) |
| `app/Http/Controllers/AuthController.php` | Refactorizar register/login/logout para usar `$this->success()` |
| `app/Http/Controllers/ClubController.php` | Refactorizar index/show/store/update/preview |
| `app/Http/Controllers/ClubMemberController.php` | Refactorizar index/store |
| `app/Http/Controllers/ClubRoleController.php` | Refactorizar index/store/update |
| `app/Http/Controllers/ClubChannelController.php` | Refactorizar index/store/update |
| `app/Http/Controllers/FriendshipController.php` | Refactorizar index/pending/store/respond |
| `app/Http/Controllers/UserController.php` | Refactorizar me/show/updateProfile/sessions |

### 5.3 No tocar

- `ClubCategoryController`, `ClubMemberRoleController`, etc. — mismo patrón que los demás, se refactorizan en la misma pasada
- Resources — ya están bien, no necesitan cambios
- `response()->noContent()` para deletes — integrado en el trait

### 5.4 Riesgos

- **Riesgo bajo**: Métodos `success()` y `error()` son inline actualmente, solo se encapsula la estructura. La lógica de negocio no cambia.
- **Riesgo medio**: `ClubController::preview` usa `array_merge` con `toArray()` — habría que decidir si meter `is_member` en el Resource o manejarlo como campo extra en el trait.
- **Sin breaking change**: El formato de respuesta no cambia, solo se centraliza su construcción.

### 5.5 Tests

- No se requieren tests nuevos para el trait (es boilerplate de response).
- Los tests existentes deben seguir pasando porque el formato de respuesta es idéntico.

### 5.6 Rollback

- Remover `use ApiResponse;` del `Controller` base y revertir cada controller a `response()->json()` manual.

---

## 6. Conclusión

El código actual **funciona** pero es **frágil y repetitivo**. Un trait `ApiResponse`:

- ✅ Elimina 300+ líneas de arrays manuales repetidos
- ✅ Unifica el formato de respuesta en un solo lugar
- ✅ Hace el contrato API explícito (el trait ES la documentación)
- ✅ Sigue el patrón de Laravel (traits en controllers)
- ✅ Cero impacto en el frontend (el JSON que llega es idéntico)
- ✅ Refactor progresivo: no necesitamos reescribir todo hoy, podemos ir migrando controller por controller

**Tiempo estimado**: 30-45 minutos (crear trait + refactor de ~12 controllers).
