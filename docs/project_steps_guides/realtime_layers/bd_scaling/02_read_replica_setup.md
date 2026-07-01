# 02 — Configurar Read Replica de PostgreSQL

> **Qué vamos a hacer**: Crear una copia de solo lectura de nuestra base de datos. El primary maneja writes (INSERT/UPDATE/DELETE), la replica maneja reads (SELECT).
> **Tiempo estimado**: 45 minutos.
> **Prerrequisitos**: PgBouncer del paso 01 funcionando, PostgreSQL primary funcionando.

---

## 🔍 ¿Qué vamos a hacer?

Arquitectura final de este paso:

```
Laravel ──► PgBouncer (6432) ──► Postgres PRIMARY (5432)  ← writes + reads

                                  └──► Postgres REPLICA (5433) ← solo reads
```

La **replica** se mantiene sincronizada con el primary via **streaming replication**. Básicamente, el primary escribe cada cambio en un "diario" (WAL), y la replica lee ese diario constantemente y aplica los mismos cambios.

---

## 📋 Prerrequisitos

```powershell
# 1. PgBouncer debe estar funcionando
docker ps | Select-String "pgbouncer"

# 2. Postgres primary debe estar funcionando
docker ps | Select-String "postgres"

# 3. Verifica que puedes conectarte al primary
docker exec -it <primary_container> psql -U postgres -c "SELECT current_setting('server_version');"
```

Deberías ver la versión de PostgreSQL (17.x).

---

## ✅ Paso 1: Configurar el primary para replicación

El primary necesita 3 cambios para permitir replicación:

### 1a. Modificar `postgresql.conf`

Agrega o modifica estos parámetros en el contenedor primary:

```conf
-- postgresql.conf (del primary)

-- Nivel de logging del WAL: 'replica' o 'logical'
-- 'replica' permite replicación física (la que usamos)
wal_level = replica

-- Número máximo de conexiones de replicación simultáneas
-- 1 por cada replica. Pon 3 para tener margen.
max_wal_senders = 3

-- Número máximo de slots de replicación
-- 1 por cada replica. Misma lógica que max_wal_senders.
max_replication_slots = 3

-- Retención mínima de WAL (segmentos)
-- 64 segmentos = ~1 GB de WAL retenido
wal_keep_size = 64

-- Para que la replica pueda hacer consultas de solo lectura mientras replica
hot_standby = on
```

### 1b. Modificar `pg_hba.conf`

Agrega una línea para permitir que la replica se conecte:

```conf
-- pg_hba.conf (del primary)

-- Permitir conexiones de replicación desde cualquier contenedor Docker
-- Tipo: host    BD: replication  Usuario: replica  IP: rango  Método: md5
host    replication     replica         172.0.0.0/8             md5
```

> [!IMPORTANT]
> El rango `172.0.0.0/8` es la red interna de Docker. Si tus contenedores usan otra red (ej: `10.0.0.0/8`), ajusta el rango. Puedes ver la IP de tu primary con: `docker inspect <primary_container> | Select-String "IPAddress"`

### 1c. Crear rol `replica` en Postgres

```bash
# Conéctate al primary y crea el usuario
docker exec -it <primary_container> psql -U postgres -c "
  CREATE ROLE replica WITH LOGIN REPLICATION PASSWORD 'replica_secret_2026';
"
```

**Explicación del comando:**
- `CREATE ROLE replica` — crea un usuario llamado "replica"
- `WITH LOGIN` — puede iniciar sesión
- `REPLICATION` — tiene permisos de replicación (puede leer el WAL)
- `PASSWORD '...'` — contraseña que usará la replica para conectarse

### 1d. Reiniciar el primary

```bash
docker restart <primary_container>
```

---

## ✅ Paso 2: Crear la replica con pg_basebackup

Ahora vamos a crear la replica usando `pg_basebackup`. Este comando hace una copia exacta del primary y la prepara para funcionar como replica.

### 2a. Preparar el directorio de datos para la replica

```bash
# Crea el directorio donde vivirá la replica
docker exec <primary_container> mkdir -p /var/lib/postgresql/replica

# Da permisos al usuario postgres
docker exec <primary_container> chown postgres:postgres /var/lib/postgresql/replica
```

### 2b. Ejecutar pg_basebackup

```bash
# Desde DENTRO del primary, ejecuta el backup
docker exec -it <primary_container> bash -c "
  pg_basebackup -h localhost \
                -U replica \
                -D /var/lib/postgresql/replica \
                -Fp \
                -Xs \
                -P -v \
                -R
"
```

**Explicación de cada flag:**

| Flag | Qué hace |
|---|---|
| `-h localhost` | Se conecta al primary en localhost |
| `-U replica` | Usa el usuario replica que creamos |
| `-D /var/lib/postgresql/replica` | Directorio donde guardar la copia |
| `-Fp` | Formato plain (archivos normales, no comprimido) |
| `-Xs` | Incluye WAL en formato stream (más rápido) |
| `-P -v` | Muestra progreso y verbose |
| `-R` | **Crea automáticamente `standby.signal` + escribe `primary_conninfo`** |

La flag `-R` es la más importante. Hace dos cosas automágicas:

1. **Crea `standby.signal`** — esto le dice a Postgres: "ejecútate como replica, no como primary"
2. **Escribe `primary_conninfo`** en `postgresql.auto.conf` — la cadena de conexión al primary

### 2c. Verificar que el backup se creó

```bash
# Deberías ver archivos similares a los de una BD de Postgres
docker exec <primary_container> ls -la /var/lib/postgresql/replica/

# Debería existir el archivo standby.signal
docker exec <primary_container> ls -la /var/lib/postgresql/replica/standby.signal

# Debería existir postgresql.auto.conf con primary_conninfo
docker exec <primary_container> cat /var/lib/postgresql/replica/postgresql.auto.conf
```

Si ves `standby.signal` y `postgresql.auto.conf`, el backup se creó correctamente.

---

## ✅ Paso 3: Configurar y levantar la replica

### 3a. Agregar la replica al docker-compose.yml

```yaml
# docker-compose.yml (fragmento - agrega este servicio)

services:
  # ... tus servicios existentes ...

  postgres:  # Primary (ya existe)
    image: postgres:17-alpine
    container_name: vyntra-postgres-primary
    ports:
      - "5432:5432"
    environment:
      POSTGRES_DB: ${DB_DATABASE:-vyntra}
      POSTGRES_USER: ${DB_USERNAME:-postgres}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - postgres_primary_data:/var/lib/postgresql/data
    command:
      - "postgres"
      - "-c"
      - "wal_level=replica"
      - "-c"
      - "max_wal_senders=3"
      - "-c"
      - "max_replication_slots=3"
      - "-c"
      - "wal_keep_size=64"
      - "-c"
      - "hot_standby=on"
    restart: unless-stopped

  postgres-replica:
    image: postgres:17-alpine
    container_name: vyntra-postgres-replica
    ports:
      - "5433:5432"  # Puerto 5433 para la replica
    environment:
      POSTGRES_DB: ${DB_DATABASE:-vyntra}
      POSTGRES_USER: ${DB_USERNAME:-postgres}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
      # Esto es importante: replica debe tener la misma pass que primary
    volumes:
      - postgres_replica_data:/var/lib/postgresql/data
    command:
      - "postgres"
      - "-c"
      - "hot_standby=on"
    depends_on:
      - postgres
    restart: unless-stopped
```

> [!NOTE]
> La configuración via `command` en docker-compose es equivalente a modificar `postgresql.conf`. Es más limpio porque no necesitas montar archivos de configuración.

### 3b. Poblar la replica con datos

```bash
# 1. Detén la replica si está corriendo (recién la creaste, no debería tener datos)
docker-compose stop postgres-replica

# 2. Copia el backup del primary al directorio de datos de la replica
#    (esto solo se hace la primera vez)
docker cp <primary_container>:/var/lib/postgresql/replica/. \
        ./docker/postgres_replica_data/

# 3. Ajusta permisos (Postgres es exigente con los permisos)
docker-compose run --rm postgres-replica chown -R postgres:postgres /var/lib/postgresql/data

# 4. Levanta la replica
docker-compose up -d postgres-replica
```

> [!CAUTION]
> **Alternativa más simple:** En vez de hacer el `pg_basebackup` manual, puedes levantar la replica desde cero y que se sincronice sola. El método más limpio es levantar la replica y ejecutar `pg_basebackup` desde un script de init. Para desarrollo, puedes hacer que la replica sea un contenedor separado con su propio volumen.

**Enfoque práctico para desarrollo (recomendado):**

```yaml
# docker-compose.yml - versión práctica para desarrollo
# La replica se crea desde cero con un script de bootstrap

  postgres-replica:
    image: postgres:17-alpine
    container_name: vyntra-postgres-replica
    ports:
      - "5433:5432"
    environment:
      POSTGRES_DB: ${DB_DATABASE:-vyntra}
      POSTGRES_USER: ${DB_USERNAME:-postgres}
      POSTGRES_PASSWORD: ${DB_PASSWORD:-secret}
      PRIMARY_HOST: postgres
      PRIMARY_PORT: 5432
      REPLICATION_USER: replica
      REPLICATION_PASSWORD: replica_secret_2026
    volumes:
      - postgres_replica_data:/var/lib/postgresql/data
      - ./docker/replica/init.sh:/docker-entrypoint-initdb.d/init.sh
    depends_on:
      - postgres
    restart: unless-stopped
```

Y el script `./docker/replica/init.sh`:

```bash
#!/bin/bash
# init.sh - Script que se ejecuta la primera vez que se levanta la replica

set -e

# Esperar a que el primary esté listo
until pg_isready -h $PRIMARY_HOST -p $PRIMARY_PORT; do
  echo "Esperando al primary..."
  sleep 2
done

# Eliminar datos existentes (si los hay)
rm -rf /var/lib/postgresql/data/*

# Hacer pg_basebackup
pg_basebackup -h $PRIMARY_HOST \
              -U $REPLICATION_USER \
              -D /var/lib/postgresql/data \
              -Fp \
              -Xs \
              -P -v \
              -R

# El -R ya crea standby.signal y primary_conninfo
echo "Replica lista!"
```

---

## ✅ Paso 4: Verificar que la replicación funciona

### En el primary

```bash
# Conéctate al primary y revisa el estado de replicación
docker exec -it vyntra-postgres-primary psql -U postgres -c "SELECT * FROM pg_stat_replication;"
```

Deberías ver algo como:

```
-[ RECORD 1 ]----+------------------------------
pid              | 123
usesysid         | 16384
usename          | replica
application_name | walreceiver
state            | streaming
sync_state       | async
write_lag        | 00:00:00.005234  # <-- 5ms de lag
flush_lag        | 00:00:00.005890
replay_lag       | 00:00:00.006123
```

**Qué significa cada columna:**

| Columna | Qué significa |
|---|---|
| `state` | `streaming` = la replica está conectada y recibiendo datos. Cualquier otro valor = problema. |
| `sync_state` | `async` = replicación asíncrona (la que usamos). `sync` = síncrona (más lenta). |
| `replay_lag` | Cuánto tiempo de retraso tiene la replica. Ideal < 10ms. |

### En la replica

```bash
# Conéctate a la replica y verifica el estado del WAL receiver
docker exec -it vyntra-postgres-replica psql -U postgres -c "SELECT * FROM pg_stat_wal_receiver;"
```

Deberías ver:

```
-[ RECORD 1 ]---------+------------------------------
pid                   | 456
status                | streaming
receive_start_lsn     | 0/3A0F0000
received_lsn          | 0/3A1F0000
last_sender_send_lag  | 00:00:00.004
last_sender_flush_lag | 00:00:00.005
```

### Prueba de replicación en vivo

```bash
# Terminal 1: Crea una tabla en el primary
docker exec -it vyntra-postgres-primary psql -U postgres -d vyntra -c "
  CREATE TABLE prueba_replica (id serial, mensaje text);
  INSERT INTO prueba_replica (mensaje) VALUES ('hola replica!');
"

# Terminal 2: Verifica que el dato llegó a la replica
docker exec -it vyntra-postgres-replica psql -U postgres -d vyntra -c "
  SELECT * FROM prueba_replica;
"

# Deberías ver: 1 | 'hola replica!'

# Terminal 2: Intenta escribir en la replica (debe fallar)
docker exec -it vyntra-postgres-replica psql -U postgres -d vyntra -c "
  INSERT INTO prueba_replica (mensaje) VALUES ('esto falla');
"

# Deberías ver: ERROR:  cannot execute INSERT in a read-only transaction
```

> [!NOTE]
> El error en el INSERT es **la confirmación de que la replica funciona correctamente**. Es de solo lectura, como debe ser.

---

## ✅ Paso 5: Medir replication lag

El **replication lag** es el tiempo que tarda un cambio del primary en aparecer en la replica. Es importante monitorearlo porque si es muy alto, los usuarios pueden ver datos inconsistentes.

```bash
# En la replica, mide cuántos segundos de retraso tiene
docker exec -it vyntra-postgres-replica psql -U postgres -d vyntra -c "
  SELECT
    EXTRACT(EPOCH FROM now() - pg_last_xact_replay_timestamp()) AS lag_segundos;
"
```

**Interpretación:**

| Valor | Significa |
|---|---|
| `< 0.01` (menos de 10ms) | Replicación perfecta. Sin lag perceptible. |
| `0.01 - 0.10` (10ms - 100ms) | Normal. La replica está al día. |
| `0.10 - 1.0` (100ms - 1s) | Moderado. Puede haber inconsistencias temporales. |
| `> 1.0` (más de 1 segundo) | **Alerta.** Revisa red, carga del primary o tamaño del WAL. |

### ¿Por qué hay lag?

1. **Carga alta en el primary** — muchas writes generan mucho WAL que la replica no alcanza a consumir.
2. **Latencia de red** — los paquetes WAL tardan en llegar.
3. **Replica lenta** — la replica no tiene suficiente CPU para aplicar los cambios.
4. **WAL acumulado** — si la replica estuvo caída, al reconectarse tiene que ponerse al día.

### Cómo reducir el lag

- Aumenta `wal_keep_size` en el primary (más WAL retenido = más tiempo para que la replica se recupere si se cae)
- Usa replicas con mismo CPU/RAM que el primary
- Monitorea con `pg_stat_replication` y `pg_stat_wal_receiver`

---

## 🚨 Solución de problemas comunes

| Problema | Causa | Solución |
|---|---|---|
| `pg_stat_replication` vacío | La replica no está conectada | Verifica `primary_conninfo` en la replica. Verifica que el rol `replica` tenga permisos `REPLICATION`. |
| `state: catchup` en lugar de `streaming` | La replica está alcanzando al primary (recién conectada) | Espera. `catchup` → `streaming` automáticamente. |
| `FATAL: no pg_hba.conf entry for replication` | Falta la línea de `pg_hba.conf` en el primary | Agrega la línea del Paso 1b y reinicia Postgres. |
| Replica no arranca: `lock file already exists` | La replica no se apagó limpiamente | Elimina `postmaster.pid` de los datos de la replica y reinicia. |
| Replication lag > 5 segundos | Carga alta o red lenta | Revisa CPU del primary. Considera replicación síncrona (no recomendado). |
| La replica se queda atrás y nunca alcanza | `wal_keep_size` muy pequeño | Aumenta `wal_keep_size` a 128 o 256. |

---

## 📚 ¿Qué aprendiste en este archivo?

1. **Streaming replication** = el primary escribe cambios en un WAL (Write-Ahead Log) y la replica lo lee constantemente para aplicar los mismos cambios.
2. **La replica es de solo lectura** — intentar escribir en ella da error. Esto es una característica, no un bug.
3. **`pg_basebackup -R`** crea la replica con la configuración necesaria automáticamente (crea `standby.signal` y `primary_conninfo`).
4. **Replication lag** normalmente es de 1-10ms. Si supera 1 segundo, hay que investigar.
5. **`pg_stat_replication`** en el primary y **`pg_stat_wal_receiver`** en la replica son tus herramientas de monitoreo.
6. **Async replication** (la que usamos) es la más rápida. La replica puede tener lag, pero el primary nunca espera por ella.

**En el próximo archivo (03_database_config_laravel.md)** vas a configurar Laravel para que automáticamente envíe los SELECTs a la replica y los INSERT/UPDATE/DELETE al primary.
