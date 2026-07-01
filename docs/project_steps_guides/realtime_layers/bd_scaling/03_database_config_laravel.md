# 03 — Configurar Laravel con Read/Write Connections

> **Qué vamos a hacer**: Decirle a Laravel: "los SELECTs envíalos a la replica (puerto 5433), los INSERT/UPDATE/DELETE al primary (puerto 5432)". Laravel lo hará automágicamente.
> **Tiempo estimado**: 20 minutos.
> **Prerrequisitos**: Replica del paso 02 funcionando, PgBouncer funcionando.

---

## 🔍 ¿Qué vamos a hacer?

Arquitectura final de este paso:

```
                    ┌──► PgBouncer (6432) ──► Postgres PRIMARY (5432) ← writes
                    │
Laravel ────────────┤
                    │
                    └──► Postgres REPLICA (5433) ← reads
```

Laravel decide automáticamente a dónde enviar cada query según el tipo de operación:

| Tipo de query | Lo que escribe Laravel | A dónde va |
|---|---|---|
| `SELECT ...` | `DB::select()` o `Model::get()` | Replica |
| `INSERT ...` | `Model::create()` o `DB::insert()` | Primary |
| `UPDATE ...` | `Model::update()` o `DB::update()` | Primary |
| `DELETE ...` | `Model::delete()` o `DB::delete()` | Primary |
| Transacciones | `DB::beginTransaction()` | Primary (no pueden ir a replica) |

---

## 📋 Prerrequisitos

```powershell
# 1. La replica debe estar funcionando
docker ps | Select-String "postgres-replica"

# 2. Puedes conectarte a la replica directamente
docker exec -it vyntra-postgres-replica psql -U postgres -d vyntra -c "SELECT 1 as replica_ok;"

# 3. PgBouncer debe estar funcionando
docker ps | Select-String "pgbouncer"
```

---

## ✅ Paso 1: Modificar `config/database.php`

Laravel permite definir conexiones de **lectura** y **escritura** separadas dentro de una misma conexión. Esto se hace en `config/database.php`.

### Antes (sin read/write splitting)

```php
// config/database.php
// Versión sin replica - todo va al mismo host

'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '6432'),
    'database' => env('DB_DATABASE', 'vyntra'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',
],
```

### Después (con read/write splitting)

```php
// config/database.php
// Versión con replica - reads van a replica, writes van a primary
// Todo pasa por PgBouncer (puerto 6432 para primary, 6433 para replica via otro PgBouncer)

'pgsql' => [
    'driver' => 'pgsql',
    'url' => env('DB_URL'),

    // ═══════════════════════════════════════════════════════════
    // IMPORTANTE: El orden importa
    // 'read' debe ir ANTES de 'host' y 'port' a nivel raíz
    // ═══════════════════════════════════════════════════════════

    // CONEXIÓN DE LECTURA (SELECTs) → va a la replica
    'read' => [
        'host' => [
            env('DB_READ_HOST', '127.0.0.1'),  // replica host
        ],
        'port' => env('DB_READ_PORT', '5433'),  // puerto de la replica
    ],

    // CONEXIÓN DE ESCRITURA (INSERTs, UPDATEs, DELETEs) → va al primary
    'write' => [
        'host' => [
            env('DB_WRITE_HOST', '127.0.0.1'),  // primary host
        ],
        'port' => env('DB_WRITE_PORT', '6432'),  // PgBouncer apunta al primary
    ],

    // ═══════════════════════════════════════════════════════════
    // OJO: 'host' y 'port' a nivel raíz se usan como FALLBACK
    // Si 'read' y 'write' están definidos, estos son el default
    // ═══════════════════════════════════════════════════════════
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '6432'),

    'database' => env('DB_DATABASE', 'vyntra'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'search_path' => 'public',
    'sslmode' => 'prefer',

    // ═══════════════════════════════════════════════════════════
    // STICKY CONNECTION: evita leer datos viejos después de escribir
    // ═══════════════════════════════════════════════════════════
    'sticky' => env('DB_STICKY', true),
],
```

### ¿Qué cambió exactamente?

```php
// ANTES
'host' => env('DB_HOST', '127.0.0.1'),
'port' => env('DB_PORT', '6432'),

// DESPUÉS - se agregaron:
'read' => [
    'host' => [env('DB_READ_HOST', '127.0.0.1')],
    'port' => env('DB_READ_PORT', '5433'),
],
'write' => [
    'host' => [env('DB_WRITE_HOST', '127.0.0.1')],
    'port' => env('DB_WRITE_PORT', '6432'),
],
'sticky' => env('DB_STICKY', true),
```

> [!IMPORTANT]
> El array `'host'` dentro de `read` y `write` puede tener **múltiples hosts**. Laravel elige uno al azar para balancear carga. Si tienes 3 replicas: `'host' => ['rep1', 'rep2', 'rep3']`. Esto lo veremos en el paso 04 (horizontal scaling).

---

## ✅ Paso 2: Variables `.env` nuevas

Agrega estas variables a tu `.env`:

```env
# .env - Nuevas variables para read/write splitting

# Host de la replica (para lecturas)
DB_READ_HOST=127.0.0.1
DB_READ_PORT=5433

# Host del primary (para escrituras, via PgBouncer)
DB_WRITE_HOST=127.0.0.1
DB_WRITE_PORT=6432

# Mantener la variable original también (fallback)
DB_HOST=127.0.0.1
DB_PORT=6432

# Sticky connections: true = después de escribir, las lecturas van al primary
# por un tiempo (evita leer datos viejos de la replica que tenga lag)
DB_STICKY=true
```

### Las variables originales vs nuevas

| Variable | Antes | Después |
|---|---|---|
| `DB_HOST` | Conectaba a Postgres directo | Fallback (no se usa si `read`/`write` existen) |
| `DB_PORT` | 5432 o 6432 | Fallback |
| `DB_READ_HOST` | No existía | IP de la replica |
| `DB_READ_PORT` | No existía | 5433 (replica directa) o 6433 (PgBouncer de replica) |
| `DB_WRITE_HOST` | No existía | IP del primary |
| `DB_WRITE_PORT` | No existía | 6432 (PgBouncer de primary) |
| `DB_STICKY` | No existía | true |

> [!TIP]
> ¿Por qué la replica va al puerto 5433 (directo) y no a un PgBouncer? Porque en desarrollo es más simple. En producción, CADA Postgres (primary y replica) debería tener su propio PgBouncer. Pero para empezar, conectar la replica directo está bien.

---

## ✅ Paso 3: Cómo Laravel decide cuándo usar read vs write

Laravel NO inspecciona la query para decidir. Usa una regla más inteligente:

### La regla interna de Laravel

```
¿Es una operación de escritura?
├── Sí → usa conexión 'write'
└── No → usa conexión 'read'
```

**¿Qué considera Laravel "operación de escritura"?**

| Método de Laravel | Conexión que usa |
|---|---|
| `DB::select()` | Read |
| `DB::insert()` | Write |
| `DB::update()` | Write |
| `DB::delete()` | Write |
| `DB::statement('SELECT ...')` | Write **(esto es trampa!)** |
| `Model::find()` | Read |
| `Model::get()` | Read |
| `Model::create()` | Write |
| `Model::update()` | Write |
| `Model::delete()` | Write |
| `Model::firstOrCreate()` | Write (porque puede crear) |
| `DB::transaction()` | Write |

> [!CAUTION]
> **Cuidado con `DB::statement()`**. Aunque le pases un SELECT, Laravel lo envía al write connection porque `statement()` está clasificado como operación de escritura. Siempre usa `DB::select()` para lecturas.

### ¿Y las transacciones?

Si haces `DB::beginTransaction()`, Laravel cambia automáticamente a la conexión de escritura (primary) y todas las queries dentro de la transacción van al primary, incluso los SELECTs. Esto es correcto porque:

1. Las transacciones necesitan consistencia (no puedes leer de la replica mientras escribes en el primary dentro de la misma transacción)
2. La replica no soporta `SELECT ... FOR UPDATE` (no puede lockear filas)
3. Postgres replica no permite transacciones de lectura/escritura

---

## ✅ Paso 4: Forzar write en una query específica

A veces necesitas que un SELECT vaya al primary. Por ejemplo:

- Acabas de crear un usuario y quieres leerlo inmediatamente (la replica puede tener lag)
- Necesitas leer datos que cambian constantemente y la consistencia es crítica
- Estás haciendo un `SELECT ... FOR UPDATE` (solo funciona en primary)

### Opción 1: `onWriteConnection()`

```php
// Forzar que este SELECT vaya al primary
$user = User::onWriteConnection()->find($uuid);

// También funciona con queries raw
$users = DB::connection()->onWriteConnection()->select('SELECT * FROM users WHERE...');
```

### Opción 2: Usar `DB::transaction()` aunque solo leas

```php
// Si necesitas consistencia de lectura después de escribir,
// meter el SELECT dentro de una transacción lo envía al primary
DB::transaction(function () {
    $user = User::find($uuid); // Esto va al primary
    // ... más operaciones ...
});
```

### Opción 3: Sticky connections (automático)

```php
// Si 'sticky' => true en config/database.php,
// Laravel automáticamente envía SELECTs al primary durante
// el mismo request si hubo una escritura antes

// En un mismo request:
$user = User::create([...]);           // → Write (primary)
$user = User::find($user->uuid);       // → Write (primary) por sticky!
$other = User::where('age', 18)->get(); // → Write (primary) por sticky!
```

### ¿Cuándo usar cada opción?

| Situación | Solución |
|---|---|
| Acabo de crear algo y quiero leerlo | No hagas nada. `sticky` lo maneja. |
| SELECT FOR UPDATE | Siempre va a write nativamente (FOR UPDATE requiere primary) |
| Lectura en job de cola (sin request previo) | Usa `onWriteConnection()` si necesitas consistencia |
| Reporte nocturno que no necesita consistencia | No hagas nada. Usa la replica. |
| Dashboard en tiempo real con datos frescos | Usa `onWriteConnection()` o sticky |

---

## ✅ Paso 5: Verificar que Laravel usa la replica

### Prueba 1: Ver la configuración cargada

```bash
php artisan tinker
```

```php
// ¿Cuál es el host de lectura?
config('database.connections.pgsql.read.host');
// Debería mostrar: ['127.0.0.1']

// ¿Cuál es el host de escritura?
config('database.connections.pgsql.write.host');
// Debería mostrar: ['127.0.0.1']

// ¿A qué puerto va la lectura?
config('database.connections.pgsql.read.port');
// Debería mostrar: 5433

// ¿A qué puerto va la escritura?
config('database.connections.pgsql.write.port');
// Debería mostrar: 6432
```

### Prueba 2: Ver el host real de una query

```php
// Crea un helper temporal en tinker
// (No hay una función nativa, pero podemos ver el log)

// Activa el log de queries
DB::enableQueryLog();

// Ejecuta una lectura
$users = User::take(1)->get();

// Mira el log
DB::getQueryLog();
```

El log no muestra a qué host fue la query, pero puedes verificar indirectamente:

```php
// Si quieres estar SEGURO de que fue a la replica,
// puedes ejecutar esto DESPUÉS de la query:

// Intenta conectar y ver el nombre del servidor
$results = DB::select("SELECT inet_server_addr() as ip, inet_server_port() as port");
// ip = 172.x.x.x (IP del contenedor que respondió)
// port = 5432 (puerto INTERNO de Postgres, no el expuesto)
```

### Prueba 3: Prueba de humo (smoke test)

```bash
# Crea datos en el primary
docker exec -it vyntra-postgres-primary psql -U postgres -d vyntra -c "
  INSERT INTO users (uuid, username, email, password, created_at, updated_at)
  VALUES ('00000000-0000-0000-0000-000000000001', 'test_replica', 'test@replica.com', 'hashed', NOW(), NOW());
"

# Ahora desde Laravel, lee el usuario (debería ir a la replica)
php artisan tinker --execute="print_r(User::where('username', 'test_replica')->first()?->toArray());"
# Si ves el usuario, la replica está sincronizada y Laravel leyó de ella
```

### Prueba 4: Verificar sticky connections

```php
// Esto crea un usuario y luego lo lee inmediatamente
// Con sticky=true, la lectura va al primary (no a la replica)

$user = User::create([
    'uuid' => '00000000-0000-0000-0000-000000000002',
    'username' => 'test_sticky',
    'email' => 'sticky@test.com',
    'password' => bcrypt('test'),
]);

// Esta lectura debería ir al primary (por sticky)
$justCreated = User::find('00000000-0000-0000-0000-000000000002');
// Existe porque leyó del primary, no de la replica (que podría tener lag)
```

---

## 🚨 Solución de problemas comunes

| Problema | Causa | Solución |
|---|---|---|
| Todas las queries van al primary | `read` host no está configurado | Verifica `config('database.connections.pgsql.read')` |
| Error: `cannot execute INSERT in a read-only transaction` | Laravel está enviando writes a la replica | Verifica que `write.host` apunte al primary y `read.host` a la replica |
| Sticky no funciona | `sticky` no está en config | Agrega `'sticky' => env('DB_STICKY', true)` |
| La replica tiene datos viejos | Replication lag | Aumenta `wal_keep_size` o revisa red entre primary y replica |
| `DB::statement('SELECT...')` va al primary | Laravel clasifica `statement()` como write | Usa `DB::select()` en vez de `DB::statement()` |

---

## 📚 ¿Qué aprendiste en este archivo?

1. **Laravel soporta read/write splitting nativamente** — solo necesitas agregar los arrays `read` y `write` en `config/database.php`.
2. **La regla de decisión es simple**: `SELECT` → read, `INSERT/UPDATE/DELETE` → write. `DB::statement()` siempre write.
3. **Sticky connections** evitan el problema de eventual consistency: después de escribir en el primary, las lecturas del mismo request también van al primary.
4. **Puedes forzar write** con `onWriteConnection()` o metiendo la query en una transacción.
5. **`DB::statement()` es trampa** — aunque le pases un SELECT, va al write. Usa `DB::select()`.
6. **El array `host` puede tener múltiples IPs** — Laravel balancea entre ellas. Esto sirve para cuando tengas varias replicas.

**En el próximo archivo (04_horizontal_scaling.md)** vas a aprender a escalar el MONOLITO horizontalmente: múltiples procesos Octane, múltiples nodos Reverb, y cómo todo se comunica.
