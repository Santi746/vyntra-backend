# ✅ Mandatory Patterns — Global Architecture

> Leer AL INICIO de cada sesión.
> La IA debe verificar las secciones relevantes ANTES de escribir código.
> Si una sección aplica pero no estás 100% seguro → STOP → pregunta (HITL).
> Violar una regla = bug en producción.

---

## 📍 Mapa rápido: qué sección aplicar según lo que haces

```
[BACKEND]
├── store() / creación        → 1, 2, 6
├── update() / PATCH          → 2, 6
├── index() / lista paginada  → 3, 4
├── show() / detalle          → 3
├── destroy() / delete        → 2
├── Modelo Eloquent           → 5
├── Migración                 → 6
├── FormRequest               → 7
├── Policy                    → 8
├── API Resource              → 9
├── Ruta / middleware         → 2
├── Infraestructura (Octane)  → 10
└── Output decision (eventos, API, logs)  → 18

[FRONTEND]
├── useQuery / hook lectura   → 11
├── useMutation / escritura   → 12, 13
├── Servicio API              → 15
├── Componente UI             → 16
├── WebSocket / Echo          → 14
├── Store Zustand             → 16
├── Feature completo          → 11, 12, 13, 14, 15, 16
└── Output decision (eventos, API, logs)  → 18

[BOTH] → 17, 18 (siempre aplica)
```

---

## [BACKEND] 1. IDEMPOTENCIA

> Aplica a: TODO `store()` que crea recursos desde frontend

- [ ] Usa `Model::firstOrCreate()` con `client_uuid` (no `Model::create()`)
- [ ] Side effects (crear relación, enviar notificación) van DENTRO de `if ($model->wasRecentlyCreated) { ... }`
- [ ] `$model->wasRecentlyCreated ? 201 : 200` para status code
- [ ] `client_uuid` tiene UNIQUE o composite UNIQUE con parent_uuid en la migración. Excepciones intencionales: dm_conversations usa `user_one_uuid + user_two_uuid`, users y pivot tables no tienen client_uuid.
- [ ] DM conversations: ordenar `user_one_uuid` y `user_two_uuid` alfabéticamente con `strcmp()`

**NO**: `ClubMember::create([...])` sin guardia de `wasRecentlyCreated`
**SÍ**: `ClubMember::firstOrCreate([...])` dentro de `if ($club->wasRecentlyCreated)`

---

## [BACKEND] 2. SRP (Controllers)

> Aplica a: TODO método en controller. El controller SOLO orquesta.

- [ ] Validación → FormRequest. **NUNCA** `$request->validate()` inline
- [ ] Autorización → `Gate::authorize()`. NUNCA `if ($user->is_admin)` inline
- [ ] Formateo respuesta → API Resource. NUNCA `toArray()` manual
- [ ] Respuesta listas → `{data: [...], meta: {next_cursor, per_page}}`
- [ ] Respuesta single → `{status: 'success', data: resource}`
- [ ] Delete → `response()->noContent()` (HTTP 204)
- [ ] `load()` relaciones ANTES de construir el Resource
- [ ] Después de DB write, antes de Resource: `dispatch(new XxxEvent(...))` o `XxxJob::dispatch()`
- [ ] **Prohibido** `broadcast()` síncrono dentro del Controller — delegar a Event + ShouldQueue
- [ ] Updates: `Arr::except($validated, ['uuid', 'client_uuid', 'id'])` antes de `->update()`
- [ ] Sin `unset($validated['campo'])` — el FormRequest solo valida lo que persiste
- [ ] Rate Limiting: rutas POST/PUT/DELETE protegidas con middleware `throttle:10,1`

**NO**: lógica de negocio, validación inline, transformación manual, broadcast() sync
**SÍ**: validar → autorizar → ejecutar DB → dispatch(Event/Job) → load relaciones → responder Resource

---

## [BACKEND] 3. N+1 PREVENTION

> Aplica a: TODO endpoint que devuelve colecciones o relaciones

- [ ] `with(['relation'])` en la query ANTES de pasar al Resource
- [ ] `load(['relation'])` en modelo existente ANTES del Resource
- [ ] Resource usa `$this->whenLoaded('relation')` para TODA relación opcional
- [ ] Resource usa `$this->whenHas('aggregate')` para `withCount()` / `withSum()` fields
- [ ] **Prohibido** acceder a `$this->relationName` sin `whenLoaded()` en el Resource
- [ ] **Prohibido** acceder a relaciones no cargadas dentro de loops

---

## [BACKEND] 4. PAGINACIÓN

> Aplica a: TODO `index()` que lista recursos

- [ ] Usa `cursorPaginate(N)`. **Prohibido** `paginate()` (offset)
- [ ] Índice compuesto en migración: `$table->index(['filter_column', 'sort_column'])`
- [ ] Formato respuesta: `{data: [...], meta: {next_cursor: string|null, per_page: int}}`
- [ ] Ordenación descendente para chat/mensajes (`orderBy('created_at', 'desc')`)

---

## [BACKEND] 5. MODELOS ELOQUENT

> Aplica a: TODO modelo nuevo o modificación de existente

- [ ] `use HasUuids;` + `protected $primaryKey = 'uuid';`
- [ ] `protected $table = 'table_name';` (explícito)
- [ ] `#[Fillable(['col1', 'col2'])]` (PHP 8 attribute, NO `$fillable` property)
- [ ] `use SoftDeletes;` en tablas de dominio (NO en pivotes)
- [ ] `use HasFactory;`
- [ ] `protected function casts(): array` (NO `$casts` property)
- [ ] `#[Hidden(['password', 'remember_token'])]` para campos sensibles
- [ ] `belongsTo()` con `foreign_key` y `'uuid'` explícitos
- [ ] `hasMany()` con `foreign_key` y `'uuid'` explícitos
- [ ] `belongsToMany()` con nombre de tabla pivote y ambas FKs explícitas

---

## [BACKEND] 6. MIGRACIONES

> Aplica a: TODA migración nueva

- [ ] `$table->uuid('uuid')->primary();` como PK
- [ ] `$table->uuid('client_uuid');` + `->unique()` o `->unique(['parent_uuid', 'client_uuid'])`
- [ ] `$table->foreignUuid('col')->constrained('table', 'uuid');` para FKs
- [ ] FKs recursivos (self-referencing): agregar en `Schema::table()` separado
- [ ] Índice compuesto para cursor: `$table->index(['filter_col', 'sort_col']);`
- [ ] Índice en FK columns (PostgreSQL no auto-indexa): `$table->index('fk_column');`
- [ ] `$table->timestamps();` + `$table->softDeletes();`
- [ ] **NUNCA** `->onDelete('cascade')` — soft deletes manejan cascada
- [ ] Migraciones organizadas en subdirectorios por módulo (`01_system/`, `02_users/`, `03_clubs/`, etc.). Cargadas via `AppServiceProvider::boot()` con `glob()`.
- [ ] ALTER migrations para índices nuevos tienen su propio archivo con fecha descriptiva (ej: `2026_05_30_000000_add_index_to_table.php`)

---

## [BACKEND] 7. FORMS REQUEST

> Aplica a: TODO FormRequest nuevo

- [ ] `authorize(): bool` → retorna `true` (delegar a Gate en controller)
- [ ] `rules()` con array syntax `['required', 'string', 'max:100']` (NO pipe `'required|string'`)
- [ ] `client_uuid` en POST: `['required', 'uuid']`
- [ ] `client_uuid` en PATCH: omitir o `['sometimes', 'string']` (se excluye del update via `Arr::except()`)
- [ ] `sometimes` en TODOS los campos de `Update*Request`
- [ ] `exists:table,column` para UUIDs que referencian otras tablas
- [ ] `Rule::notIn([$this->user()->uuid])` para prevenir auto-referencia
- [ ] `prepareForValidation()` para normalizar datos antes de validar (emails lowercase, mapping)
- [ ] `after()` para validación post-hook que requiere DB (ej: verificar password actual)
- [ ] `in:val1,val2` para campos con valores fijos (status, type, action)

---

## [BACKEND] 8. POLICIES

> Aplica a: TODA Policy nueva

- [ ] `viewAny()` / `view()` → verificar membresía: `$club->members()->where('user_uuid', $user->uuid)->exists()`
- [ ] `create()` / `update()` / `delete()` → verificar bitmask: `$user->hasClubPermission($club, ClubPermission::XXXXXXXX)`
- [ ] Owner bypass: `$club->owner_uuid === $user->uuid` para operaciones irreversibles (delete club, etc.)
- [ ] Políticas de DM: comparación directa de UUIDs, SIN DB query
- [ ] Políticas de Notification: comparación directa de `user_uuid`
- [ ] Registrar en `AppServiceProvider::boot()` con `Gate::policy(Model::class, Policy::class)`
- [ ] Bitmask Permission como clase PHP con `const` + `1 << N` (NO PHP `enum` — necesita operadores bitwise nativos)
- [ ] `User::hasClubPermission()` como resolvedor centralizado: owner bypass → ADMINISTRATOR bypass → bitmask AND
- [ ] `outranks()` en ClubRolePolicy: previene asignar permisos que el usuario no posee. Verifica que la máscara del usuario CONTENGA todos los bits del rol destino.
- [ ] Custom Gate methods (`viewPrivateChannels`) definidos en Policy pero llamados via `Gate::allows()` desde controllers

---

## [BACKEND] 9. API RESOURCES

> Aplica a: TODO Resource nuevo

- [ ] `@property-read ModelType $resource` docblock
- [ ] Resource retorna array plano. El Controller agrega `'data' => ...`
- [ ] Casting explícito: `(string)` en TODOS los UUIDs, FKs y `client_uuid`; `(bool)` en flags; `(int)` en numéricos
- [ ] Fechas ISO 8601: `$this->created_at->toIso8601String()` (NO `toISOString()` — ambas son ISO 8601, se estandarizó el primero)
- [ ] `$this->whenLoaded('relation')` para TODA relación opcional
- [ ] `$this->whenHas('aggregate')` para `withCount()` / `withSum()` fields
- [ ] Nullsafe para timestamps opcionales: `$this->created_at?->toIso8601String()`
- [ ] NUNCA inventar nombres de campo — deben coincidir con columna DB o relación del modelo
- [ ] Denormalización controlada: ClubMemberResource extrae username/avatar del user anidado. FriendshipResource tiene dos modos de salida según relaciones cargadas.

---

## [BACKEND] 10. OCTANE / REDIS

> Aplica a: TODO código que correrá en Octane

- [ ] **Prohibido** propiedades estáticas mutables en controllers/services
- [ ] **Prohibido** `request()->user()` o `auth()->id()` en modelos
- [ ] **Prohibido** `broadcast()` síncrono en controllers — usar `ShouldBroadcast` + `ShouldQueue`
- [ ] Prohibido almacenar estado de usuario en singletons

---

## [FRONTEND] 11. REACT QUERY — LECTURAS

> Aplica a: TODO hook que obtiene datos del servidor

- [ ] `useQuery()` para entidad única: `queryKey: ['entity', uuid]`, `enabled: !!uuid`
- [ ] `useInfiniteQuery()` para listas: `initialPageParam: null`, `getNextPageParam: (lastPage) => lastPage.meta?.next_cursor`
- [ ] `queryFn` llama al service y unwrappa: `const res = await XxxService.method(); return res.data;`
- [ ] Global: `staleTime: 1000 * 60 * 5`, `retry: 1`, `refetchOnWindowFocus: false`
- [ ] NUNCA `useState` para datos del servidor — todo en React Query cache

---

## [FRONTEND] 12. MUTACIONES — PROTOCOLO VYNE

> Aplica a: TODA mutación que escribe datos en backend

- [ ] `useMutation({ mutationFn, onMutate, onError, onSettled })` — los 3 ciclos de vida
- [ ] `onMutate`: cancel refetches → backup cache → inject optimista
- [ ] `onError`: rollback cache desde `context.previousXxx`
- [ ] `onSettled`: `queryClient.invalidateQueries({ queryKey: [...] })`
- [ ] Objeto optimista con estado transitorio: `{ status: 'sending' | 'creating' | 'saving' }`
- [ ] `mutationFn` recibe parámetros nombrados (destructured object)
- [ ] Sonner toast: `toast.success('Título')`, `toast.error('Título', { description: err.message })`

---

## [FRONTEND] 13. DEDUPLICACIÓN — UUID CLIENTE

> Aplica a: TODA creación de recurso que viene del frontend

- [ ] `client_uuid` generado en frontend ANTES de llamar `mutate()` (con `generateClientUUID()`)
- [ ] El optimista usa `client_uuid` como `uuid` temporal
- [ ] El service retorna `{ uuid: client_uuid, client_uuid }` para dedup
- [ ] WebSocket: si `client_uuid` ya existe en caché → actualizar status, NO duplicar como nuevo

---

## [FRONTEND] 14. WEBSOCKETS / ECHO

> Aplica a: TODO código que escucha eventos en tiempo real

- [ ] Listeners modifican React Query cache, NUNCA la UI directamente
- [ ] Reconexión: invalidar queries activas (`queryClient.invalidateQueries`)
- [ ] Chat/mensajes → payload completo (el Resource completo)
- [ ] Estructural (clubs, roles) → payload mínimo `{ uuid, action }`

---

## [FRONTEND] 15. SERVICIOS API

> Aplica a: TODO service file

- [ ] Exportar como objeto: `export const XxxService = { async method() { ... } }`
- [ ] JSDoc: `@service` en archivo, `@param`/`@returns` en cada método
- [ ] `await mockRequest(delay)` al inicio de cada método
- [ ] Envoltorio estándar: retorna `{ status, data, meta }`
- [ ] snake_case en propiedades que reflejan el backend
- [ ] `client_uuid` se retorna como `uuid` y `client_uuid` en create

---

## [FRONTEND] 16. COMPONENTES / UI

> Aplica a: TODO componente nuevo

- [ ] UI kit → `src/shared/components/ui/{atoms,molecules,organisms}/`
- [ ] Feature components → `src/features/{feature}/components/{organisms,templates}/`
- [ ] `"use client"` solo si hay hooks, eventos, estado, o browser APIs
- [ ] Modales: `Portal` + `ModalOverlay` + `ModalShell` + `AnimatePresence`
- [ ] Infinite scroll: `InfiniteScrollTrigger` (intersection observer) + `fetchNextPage`
- [ ] Server data → React Query + Zustand (NUNCA useState para datos del servidor)
- [ ] Zustand stores con `persist` middleware + `partialize` para excluir estado transitorio de la persistencia
- [ ] Naming: PascalCase para componentes, camelCase para hooks/funciones

---

## [BOTH] 17. NAMING CONVENTIONS

> Aplica SIEMPRE, todo el código

- [ ] snake_case para propiedades de datos (backend + frontend)
- [ ] PascalCase para componentes y archivos de componentes
- [ ] camelCase para funciones, métodos, hooks
- [ ] UPPER_SNAKE_CASE para constantes
- [ ] kebab-case para URLs y rutas
- [ ] Comentarios en español (consistente con el proyecto)

---

## [BOTH] 18. YAGNI — Principio de Mínimo Output

> Aplica a: TODO momento donde un sistema expone/envía datos (API, eventos WebSocket, logs, permisos, columnas de DB)

**Definición:** "Solo enviar/exponer lo que el consumidor va a usar HOY. Si en duda, enviar menos — siempre podés agregar más después. Sacar campos públicos de una API es breaking change."

**Contexto histórico del proyecto:** Este principio se formalizó durante la feature de Eventos Broadcast (ver `docs/architecture/realtime_architecture/`) cuando decidimos usar `Resource::resolve()` para eventos de chat/DM/notificación (Patrón A/B) y array manual `{uuid, action}` para eventos de delete/update parcial (Patrón C/D). Discord aplica el mismo principio en su Gateway.

### Backend (Laravel)

- [ ] **Eventos WebSocket — Patrón A (chat/DM/notif):** `Resource::resolve()` en `'d'` → el cliente necesita el objeto completo para renderizar sin fetch
- [ ] **Eventos WebSocket — Patrón B (CRUD estructural):** `Resource::resolve()` en `'d'` → el cliente actualiza cache local
- [ ] **Eventos WebSocket — Patrón C (Delete):** array manual `{uuid, action: 'delete'}` → el cliente solo necesita QUÉ borrar
- [ ] **Eventos WebSocket — Patrón D (Update parcial):** array manual `{uuid, campo_modificado}` → el cliente solo necesita QUÉ CAMBIÓ
- [ ] **Eventos WebSocket — Patrón E (Social):** array manual con UUIDs sueltos → no hay modelo único que serializar
- [ ] **API Resources:** `$this->whenLoaded('relation')` para TODA relación opcional → no enviar relaciones no pedidas
- [ ] **Form Requests:** `$validated` solo los campos permitidos, NUNCA `$request->all()`
- [ ] **Permissions:** Bitmask permissions, NUNCA `is_admin = true`
- [ ] **Database:** Soft delete selectivo, NUNCA borrar fila si solo querés esconderla
- [ ] **Logs:** Loggear evento + contexto mínimo, NUNCA payload completo (PII)
- [ ] **API responses:** NUNCA exponer columnas sensibles (password, tokens, internal_id)

### Frontend (Next.js)

- [ ] **React Query:** `select` solo los campos que el componente renderiza
- [ ] **WebSocket listeners:** Modifican cache local con `setQueryData`, NUNCA refetch global innecesario
- [ ] **Zustand stores:** Solo estado verdaderamente global, NUNCA duplicar server state
- [ ] **API services:** Retornar estructura `{status, data, meta}` sin campos extra

### Pregunta clave (aplicar ANTES de cualquier output)

> **"¿El consumidor va a usar ESTE campo? ¿Lo necesita para esta acción específica?"**
>
> - SI SÍ → incluirlo
> - SI NO → omitirlo
> - SI NO SÉ → omitirlo (siempre podés agregar después)

### Anti-patrones comunes

**NO**: Mandar el Resource completo en eventos de Delete
```php
// ❌ El cliente solo necesita saber QUÉ borrar
'd' => (new CategoryResource($this->category))->resolve(),
```

**SÍ**: Array manual con `action`
```php
// ✅ El cliente recibe solo lo que necesita
'd' => [
    'uuid' => (string) $this->category->uuid,
    'action' => 'delete',
],
```

**NO**: Cargar TODAS las relaciones en un Resource
```php
// ❌ Wasted bandwidth si el cliente no las usa
return [
    'uuid' => $this->uuid,
    'user' => new UserResource($this->user), // siempre se carga
    'club' => new ClubResource($this->club), // siempre se carga
];
```

**SÍ**: `whenLoaded()` para relaciones opcionales
```php
// ✅ Solo se serializa si fue cargada explícitamente
return [
    'uuid' => $this->uuid,
    'user' => new UserResource($this->whenLoaded('user')),
    'club' => new ClubResource($this->whenLoaded('club')),
];
```

### Regla de los 3 criterios para tomar la decisión

Antes de elegir entre Resource completo o array manual, preguntate:

1. **¿El cliente va a renderizar el objeto entero?** → Resource
2. **¿El cliente solo necesita un identificador para invalidar/refetch?** → `{uuid, action}`
3. **¿El cliente solo necesita un campo específico que cambió?** → `{uuid, campo}`

**Las 3 respuestas son mutuamente excluyentes** — solo una aplica por evento.

---

## 📋 Reglas de verificación para la IA

1. Lee este archivo AL INICIO de cada sesión
2. Antes de escribir código, identifica qué secciones del mapa aplican
3. Marca mentalmente cada checkbox mientras escribes código
4. Si una regla no se puede cumplir o no aplica → preguntar al usuario (HITL hitl_protocol.md)
5. Si después de escribir descubres que violaste una regla → refactoriza antes de continuar
6. Lo que no cubra el mapa → aplica critical thinking + HITL (AGENTS.md §2 + §3 + hitl_protocol.md)
