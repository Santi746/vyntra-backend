# 01 — Instalar PgBouncer (Connection Pooler)

> **Qué vamos a hacer**: Poner un "recepcionista" (PgBouncer) entre Laravel y PostgreSQL. Laravel hablará con PgBouncer, y PgBouncer manejará las conexiones reales a Postgres.
> **Tiempo estimado**: 30 minutos.
> **Prerrequisitos**: Docker corriendo, PostgreSQL funcionando (puede ser tu instancia local o de desarrollo).

---

## 🔍 ¿Qué vamos a instalar?

Arquitectura final de este paso:

```
Antes:
Laravel ──► PostgreSQL (conexión directa, puerto 5432)

Después:
Laravel ──► PgBouncer (puerto 6432) ──► PostgreSQL (puerto 5432)
```

PgBouncer es un proceso muy liviano (~5 MB de RAM) que se sienta entre tu app y la BD. No modifica las queries, no toca los datos. Solo **maneja las conexiones**.

---

## 📋 Prerrequisitos

Antes de empezar, verifica que tienes:

```powershell
# Verifica que Postgres está corriendo
docker ps | Select-String "postgres"

# Verifica que puedes conectarte directamente
docker exec -it <nombre_contenedor_postgres> psql -U postgres -c "SELECT 1 as test;"
```

Si ves `ERROR: connection refused`, primero asegúrate de que Postgres esté corriendo.

---

## ✅ Paso 1: Agregar PgBouncer al docker-compose.yml

Agrega este servicio a tu `docker-compose.yml`:

```yaml
# docker-compose.yml (fragmento - agrega este servicio)

services:
  # ... tus otros servicios existentes ...

  pgbouncer:
    image: bitnami/pgbouncer:latest
    container_name: vyntra-pgbouncer
    ports:
      - "6432:6432"
    environment:
      - POSTGRESQL_HOST=postgres        # nombre del servicio de postgres
      - POSTGRESQL_PORT=5432
      - POSTGRESQL_USERNAME=postgres
      - POSTGRESQL_PASSWORD=${DB_PASSWORD:-secret}
      - POSTGRESQL_DATABASE=${DB_DATABASE:-vyntra}
      - PGBOUNCER_POOL_MODE=transaction     # <-- MODO TRANSACTION (crucial)
      - PGBOUNCER_MAX_CLIENT_CONN=1000      # clientes esperando en recepción
      - PGBOUNCER_DEFAULT_POOL_SIZE=25      # conexiones reales a Postgres
      - PGBOUNCER_MIN_POOL_SIZE=5           # conexiones siempre abiertas
      - PGBOUNCER_RESERVE_POOL_SIZE=5       # pool extra para bursts
      - PGBOUNCER_RESERVE_POOL_TIMEOUT=5    # segundos antes de usar reserve
    depends_on:
      - postgres
    restart: unless-stopped
```

### Explicación de cada variable

| Variable | Valor | ¿Qué hace? |
|---|---|---|
| `PGBOUNCER_POOL_MODE` | `transaction` | Asigna conexión Postgres solo durante transacciones. Fuera de transacción, las queries pueden ir a cualquier conexión del pool. |
| `PGBOUNCER_MAX_CLIENT_CONN` | `1000` | Máximo de clientes (Laravel) que pueden estar conectados a PgBouncer simultáneamente. Si llega el 1001, espera. |
| `PGBOUNCER_DEFAULT_POOL_SIZE` | `25` | Conexiones reales a Postgres. Postgres solo verá 25 conexiones, sin importar cuántos clientes tengas. |
| `PGBOUNCER_MIN_POOL_SIZE` | `5` | Conexiones que PgBouncer mantiene abiertas aunque no haya tráfico. Reduce latencia en momentos de baja carga. |
| `PGBOUNCER_RESERVE_POOL_SIZE` | `5` | Conexiones extra que se activan cuando el pool principal está lleno y hay clientes esperando. |

> [!TIP]
> `DEFAULT_POOL_SIZE=25` es un número mágico. Postgres puede manejar ~25 conexiones activas eficientemente. Si pones 100, empiezas a tener contención de CPU. Si pones 5, el pool se satura rápido. 25 es el sweet spot para empezar.

---

## ✅ Paso 2: Configurar pgbouncer.ini (versión manual)

Si no usas la imagen de Bitnami y prefieres configuración manual, crea `pgbouncer/pgbouncer.ini`:

```ini
; pgbouncer/pgbouncer.ini
; Este archivo le dice a PgBouncer cómo comportarse

[databases]
; Mapeo: nombre_bd = host=... port=... dbname=...
vyntra = host=postgres port=5432 dbname=vyntra

[pgbouncer]
; Puerto donde escucha PgBouncer (Laravel se conecta aquí)
listen_port = 6432
listen_addr = 0.0.0.0

; Modo transaction (NO session)
pool_mode = transaction

; Tamaños de pool
default_pool_size = 25
min_pool_size = 5
reserve_pool_size = 5
reserve_pool_timeout = 5.0
max_client_conn = 1000

; Tiempo máximo de espera en cola (segundos)
client_login_timeout = 60
query_timeout = 30

; Archivo de autenticación
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt

; Archivo de log
logfile = /var/log/pgbouncer/pgbouncer.log
pidfile = /var/run/pgbouncer/pgbouncer.pid
```

---

## ✅ Paso 3: Configurar userlist.txt

Crea `pgbouncer/userlist.txt` con las credenciales de Postgres:

```txt
; pgbouncer/userlist.txt
; Formato: "username" "password"
; Debe coincidir con las credenciales de PostgreSQL

"postgres" "md5d7a6e9d8c9e9b7a8f6c4e3d2a1b0c9f8"
"vyntra_app" "md5e8b7f6a5d4c3b2a1f0e9d8c7b6a5f4e3"
```

> [!CAUTION]
> La contraseña en userlist.txt debe estar en formato **md5** (no texto plano). Para generar el hash md5 de Postgres: `echo -n "passwordusername" | md5sum` → el resultado es `md5` + hash.
>
> Alternativa: Pon la contraseña en texto plano y usa `auth_type = plain` en pgbouncer.ini. No es recomendado para producción, pero sirve para pruebas.

**Con la imagen Bitnami este archivo se genera automáticamente** si pasas `POSTGRESQL_PASSWORD` y `POSTGRESQL_USERNAME` como variables de entorno. Usa la imagen Bitnami a menos que necesites personalización extrema.

---

## ✅ Paso 4: Conectar Laravel a PgBouncer

Ahora viene lo bueno: decirle a Laravel que hable con PgBouncer en vez de directo a Postgres.

### Modificar `.env`

```env
# Antes (conexión directa a Postgres)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432        # <-- Puerto de Postgres directo
DB_DATABASE=vyntra
DB_USERNAME=postgres
DB_PASSWORD=secret

# Después (conexión via PgBouncer)
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=6432        # <-- Puerto de PgBouncer (NO de Postgres)
DB_DATABASE=vyntra
DB_USERNAME=postgres
DB_PASSWORD=secret
```

**El único cambio es `DB_PORT` de 5432 a 6432.** Laravel no sabe que está hablando con PgBouncer. Cree que es Postgres. Y funciona exactamente igual.

### Verificar conexión

```bash
# Prueba rápida desde Laravel (artisan tinker)
php artisan tinker
```

```php
// Dentro de tinker, ejecuta:
DB::select('SELECT 1 as conexion_ok');

// Deberías ver:
// [
//   {#xxx
//     +"conexion_ok": 1,
//   }
// ]

// Ahora verifica a qué puerto te conectaste:
config('database.connections.pgsql.port');
// Debería mostrar: 6432
```

Si ves `"conexion_ok": 1`, ¡PgBouncer está funcionando!

---

## ✅ Paso 5: Verificar que PgBouncer funciona correctamente

### Ver desde PgBouncer (SHOW POOLS)

```bash
# Conéctate a PgBouncer (no a Postgres) y ejecuta SHOW POOLS
docker exec -it vyntra-pgbouncer psql -U postgres -d pgbouncer -c "SHOW POOLS;"
```

El comando `SHOW POOLS` te muestra el estado interno de PgBouncer:

```
  database  |  user   | cl_active | cl_waiting | sv_active | sv_idle | sv_used
------------+---------+-----------+------------+-----------+---------+---------
 vyntra     | postgres|     3     |     0      |     2     |   23    |    0
 pgbouncer  | postgres|     1     |     0      |     0     |    0    |    0
```

**Columnas importantes:**

| Columna | Qué significa | Valor ideal |
|---|---|---|
| `cl_active` | Clientes (Laravel) ejecutando una query ahora mismo | Variable |
| `cl_waiting` | Clientes esperando que una conexión se libere | **0** (siempre debería ser 0) |
| `sv_active` | Conexiones a Postgres ejecutando algo | <= default_pool_size |
| `sv_idle` | Conexiones a Postgres inactivas (esperando trabajo) | Variable |
| `sv_used` | Conexiones a Postgres asignadas pero en pausa | 0 idealmente |

### Ver estadísticas (SHOW STATS)

```bash
docker exec -it vyntra-pgbouncer psql -U postgres -d pgbouncer -c "SHOW STATS;"
```

Te muestra cuántas queries pasaron por PgBouncer, cuántas fallaron, cuánto tiempo tomaron.

### Prueba de estrés simple

Abre **3 terminales** y en cada una ejecuta:

```bash
# Terminal 1, 2 y 3
docker exec -it vyntra-pgbouncer psql -U postgres -d vyntra -c "SELECT pg_sleep(5);"
```

Las 3 se ejecutan simultáneamente. Ahora verifica SHOW POOLS:

```bash
docker exec -it vyntra-pgbouncer psql -U postgres -d pgbouncer -c "SHOW POOLS;"
```

Deberías ver `sv_active = 3`. Postgres solo ve 3 conexiones activas, aunque tú lanzaste 3 queries "simultáneas" desde 3 terminales.

---

## 🚨 Solución de problemas comunes

| Problema | Causa más probable | Solución |
|---|---|---|
| `FATAL: no PostgreSQL user name specified` | No se pudo autenticar | Verifica `userlist.txt` y `auth_type` |
| `FATAL: password authentication failed` | Contraseña incorrecta | Genera el hash md5 correctamente |
| `ERROR: prepared statement "pdo_stmt_xxx" already exists` | Modo transaction + prepared statements | No uses `DB::statement('PREPARE...')`. Si es de un paquete, considera modo session |
| `cl_waiting` > 0 constante | Pool muy pequeño para tu carga | Aumenta `default_pool_size` a 50 o 75 |
| Conexiones directas a Postgres (no pasan por PgBouncer) | Olvidaste cambiar el puerto | Verifica `DB_PORT` en `.env` |

---

## 📚 ¿Qué aprendiste en este archivo?

1. **PgBouncer** se instala como un servicio Docker entre Laravel y Postgres. Es liviano y transparente.
2. **Pool mode = transaction** asigna conexiones Postgres solo durante transacciones, no por sesión.
3. **default_pool_size = 25** limita las conexiones reales a Postgres. Postgres nunca ve más de 25 conexiones simultáneas.
4. **max_client_conn = 1000** permite que hasta 1,000 Laravel workers se conecten a PgBouncer (aunque solo 25 estén activos).
5. **Solo cambias `DB_PORT`** de 5432 a 6432 en Laravel. Nada más.
6. **`SHOW POOLS`** es tu mejor amigo para diagnosticar. Si `cl_waiting` > 0, el pool es pequeño.
7. **Prepared statements persistentes** pueden fallar en modo transaction. Laravel no los usa por defecto, así que no deberías tener problemas.

**En el próximo archivo (02_read_replica_setup.md)** vas a configurar una read replica de PostgreSQL para que las lecturas no compitan con las escrituras.
