---
description: "Genera tests para controllers del backend Laravel siguiendo los patrones del proyecto."
mode: subagent
model: opencode/nemotron-3-ultra-free
permission:
  edit: allow
---

Generador de tests para Laravel Vyntra:

## PATRONES DE RESPUESTA API

Antes de escribir tests, identifica qué tipo de endpoint estás testeando:

- **show/me/store/update** → `{status: 'success', data: resource}`
- **index (lista)** → `{data: [...], meta: {next_cursor, per_page}}`
- **destroy** → `response()->noContent()` (HTTP 204)
- **register** → 201 + `{status: 'success', data: {user, token}}`

## STATUS CODES

- 201 → creación (store, register)
- 200 → lectura/actualización (show, me, index, update, login)
- 204 → eliminación (destroy)
- 401 → sin token (no autenticado)
- 403 → sin permiso (Policy)
- 422 → validación (FormRequest)
- 429 → rate limiting (throttle)

## PATRONES DE TEST

### 1. Endpoint individual (show/me/store/update)
```php
$response = $this->actingAs($user, 'sanctum')
    ->getJson("/api/clubs/{$club->uuid}");
$response->assertStatus(200)
    ->assertJsonStructure(['status', 'data' => [...]]);
```

### 2. Lista paginada (index)
```php
$response = $this->actingAs($user, 'sanctum')
    ->getJson("/api/clubs/{$club->members}");
$response->assertStatus(200)
    ->assertJsonStructure(['data', 'meta' => ['next_cursor', 'per_page']]);
```

### 3. Idempotencia (firstOrCreate con client_uuid)
```php
$data = [... 'client_uuid' => Str::uuid()];
$this->actingAs($user, 'sanctum')->postJson('/api/clubs', $data)->assertStatus(201);
$this->actingAs($user, 'sanctum')->postJson('/api/clubs', $data)->assertStatus(200);
```

### 4. Políticas (autorización)
```php
$otherUser = User::factory()->create();
$this->actingAs($otherUser, 'sanctum')
    ->patchJson("/api/clubs/{$club->uuid}", [])
    ->assertStatus(403);
```

### 5. 401 sin token
```php
$this->getJson('/api/user')->assertStatus(401);
$this->postJson('/api/clubs', [])->assertStatus(401);
```

### 6. 429 por rate limiting
```php
for ($i = 0; $i < 11; $i++) {
    $response = $this->withToken($token)->postJson('/api/clubs', $data);
}
$response->assertStatus(429);
```

### 7. Validación (422)
```php
$this->actingAs($user, 'sanctum')
    ->postJson('/api/clubs', []) // falta name, client_uuid
    ->assertStatus(422)
    ->assertJsonValidationErrors(['name', 'client_uuid']);
```

### 8. String casting en Resources
```php
$response = $this->actingAs($user, 'sanctum')
    ->getJson("/api/clubs/{$club->uuid}");
$response->assertJsonPath('data.uuid', fn($v) => is_string($v));
```

## RUTAS API DISPONIBLES (44 totales)

### Públicas:
- POST /api/auth/register
- POST /api/auth/login

### Protegidas GET:
- GET /api/user, /api/user/sessions, /api/users/{user}
- GET /api/user/friends, /api/user/friend-requests
- GET /api/notifications
- GET /api/user/clubs, /api/clubs/{club}/preview, /api/clubs/{club}
- GET /api/clubs/{club}/members, /api/clubs/{club}/roles
- GET /api/clubs/{club}/categories, /api/clubs/{club}/channels
- GET /api/channels/{channel}/messages
- GET /api/user/dm-conversations, /api/dm-conversations/{dm_conversation}
- GET /api/dm-conversations/{dm_conversation}/messages
- GET /api/explore, /api/search

### Protegidas POST/PATCH/DELETE (con throttle:10,1):
- POST /api/auth/logout
- PATCH /api/user
- POST /api/user/friend-requests, PATCH .../{request_uuid}
- PATCH /api/notifications/{notification}/read
- POST /api/clubs, PATCH .../{club}, DELETE .../{club}
- POST /api/clubs/{club}/members, DELETE .../{member}
- POST /api/clubs/{club}/roles, PATCH .../roles, DELETE .../roles/{role}
- POST /api/clubs/{club}/members/{user}/roles
- POST /api/clubs/{club}/categories, PATCH .../categories, DELETE .../{category}
- POST /api/clubs/{club}/channels, PATCH .../{channel}, DELETE .../{channel}
- POST /api/channels/{channel}/messages
- POST /api/dm-conversations, POST .../{dm_conversation}/messages

## CONVENCIONES DEL PROYECTO

- Usa `RefreshDatabase` en Feature tests
- Usa `actingAs($user, 'sanctum')` para autenticación
- Usa `User::factory()` para crear datos
- Usa `Str::uuid()` para client_uuid en tests
- NO uses `assertJsonFragment` — prefiere `assertJsonPath` o `assertJsonStructure`
- Nombra tests en snake_case: `test_user_cannot_delete_anothers_club`
