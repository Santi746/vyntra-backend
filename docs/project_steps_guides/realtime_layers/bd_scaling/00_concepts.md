# 00 — Conceptos de Escalabilidad de BD para Juniors

> **Profesor**: Tu mentor senior de software.
> **Estudiante**: Tú. Primera vez que escalas una base de datos.
> **Misión**: Entender qué es un pooler, una replica y escalado horizontal, sin morir en el intento.
> **Tiempo estimado**: 25 minutos de lectura.

---

## 📚 ¿Por qué Vyntra necesita escalar la BD?

Imagina que Vyntra es un restaurante. Al principio, con 10 clientes, el cocinero (PostgreSQL) puede atender a todos sin problemas. Un mes después tienes 1,000 clientes. El cocinero empieza a tardar porque:

- Tiene que **atender a cada cliente** (abrir conexión, recibir query, procesarla, devolver resultado)
- Cada cliente **ocupa una mesa** (conexión a la BD). Si hay 1,000 mesas, el cocinero se vuelve loco
- Algunos clientes solo **leen el menú** (SELECT), otros **piden platos nuevos** (INSERT/UPDATE)

**Vyntra necesita escalar la BD porque:**

| Situación actual | Número |
|---|---|
| Usuarios concurrentes | ~10K |
| Conexiones WebSocket activas | 30K (target) |
| HTTP requests / segundo (burst) | 60K |
| Ratio lectura/escritura | ~70% reads, ~30% writes |
| PostgreSQL sin pooler | ~1,000 conexiones máx. |

La matemática es simple: **60K HTTP/s + 30K WS = 90K puntos de entrada. Postgres solo acepta ~1,000 conexiones directas.** Algo tiene que mediar. Ese algo es PgBouncer.

---

## 📚 ¿Qué es un connection pooler? (PgBouncer)

### La analogía del recepcionista del restaurante

Sin pooler, cada vez que un cliente (Laravel) quiere preguntar algo a la base de datos, va directo a la cocina. Si hay 100 clientes, hay 100 personas en la cocina. Caos.

**PgBouncer es el recepcionista.** Está en la entrada del restaurante:

```
Clientes (Laravel) → Recepcionista (PgBouncer) → Cocina (PostgreSQL)
```

- Los clientes hablan con el recepcionista (PgBouncer escucha en puerto 6432)
- El recepcionista tiene **25 cocineros** (conexiones reales a Postgres en `default_pool_size`)
- Cuando un cliente termina de pedir (commit/rollback de transacción), el recepcionista asigna ese cocinero al siguiente cliente

### ¿Qué ganas con esto?

| Métrica | Sin PgBouncer | Con PgBouncer |
|---|---|---|
| Conexiones máximas a Postgres | ~1,000 (Postgres se ahoga) | 25-100 (controlado) |
| Clientes concurrentes atendidos | 1,000 | 25,000+ (con cola) |
| Memoria en Postgres | Alta (cada conexión = ~2-5 MB) | Baja (pool pequeño) |
| Creación de conexiones nuevas | Cada request (lenta) | Casi cero (reutiliza) |

### Pool modes: session vs transaction

PgBouncer tiene 3 modos. Solo nos importan 2:

| Modo | Qué hace | ¿Para Vyntra? |
|---|---|---|
| **session** | Mantiene la misma conexión Postgres durante toda la sesión del cliente | ❌ Muchas conexiones abiertas |
| **transaction** | Asigna conexión Postgres solo durante una transacción. Cuando haces COMMIT/ROLLBACK, la devuelve al pool | ✅ El que usamos |
| **statement** | Asigna conexión por statement individual | ❌ No recomendado |

**En modo `transaction`:** Si Laravel ejecuta 3 SELECTs fuera de transacción, todos pueden ir a **diferentes** conexiones del pool. Esto es seguro porque no hay estado entre queries fuera de transacción.

> [!CAUTION]
> **Prepared statements NO funcionan en modo transaction.** Si usas `DB::statement('PREPARE...')`, fallará. Laravel no usa prepared statements persistentes por defecto, así que no deberías tener problemas. Pero si ves errores de `prepared statement "pdo_stmt_xxx" already exists`, esa es la causa.

---

## 📚 ¿Qué es una read replica?

### La analogía de la biblioteca con sala de lectura separada

Imagina una biblioteca con un solo bibliotecario (PostgreSQL primary). Cuando llegan 50 personas a preguntar, el bibliotecario se satura. **Solución:** agregamos una sala de lectura separada (read replica).

```
Bibliotecario original (Primary): Escribe en el libro original.
Sala de lectura (Replica): Tiene una COPIA del libro. Solo se puede LEER.
```

**Cómo funciona en Postgres (Streaming Replication):**

1. El **primary** recibe writes (INSERT, UPDATE, DELETE)
2. El primary escribe cada cambio en un **WAL** (Write-Ahead Log) — piensa en un "diario de cambios"
3. La **replica** lee el WAL del primary constantemente y aplica los mismos cambios
4. La **replica** tiene una copia exacta de la BD, con ~milisegundos de retraso

### ¿Qué ganas con replicas?

| Métrica | 1 primary solo | 1 primary + 1 replica |
|---|---|---|
| Reads totales | ~1,000 qps (se satura) | ~50,000 qps |
| Writes | ~5,000 qps | ~5,000 qps (no cambia) |
| Latencia de lectura | Normal | Normal (si replica está al día) |
| Tolerancia a fallos | Si primary muere → caída total | Si primary muere → promover replica |

### ¿Qué NO es una read replica?

- **No es un backup.** Si alguien hace `DROP TABLE` en primary, la replica también lo ejecuta.
- **No es para writes.** Intentar escribir en la replica da error `cannot execute INSERT in a read-only transaction`.
- **No es instantánea.** La replicación tiene lag. Normalmente 1-10ms. En burst pueden ser ~100ms.

> [!IMPORTANT]
> **Eventual consistency.** Si un usuario escribe un mensaje y luego hace refresh, puede que la replica aún no tenga el dato. Laravel tiene "sticky connections" para mitigar esto (lo verás en el paso 03).

---

## 📚 ¿Qué es escalado horizontal?

### La analogía de las cajas registradoras

Tienes un supermercado con una sola caja (1 servidor). La cola crece. Tienes dos opciones:

- **Escalado vertical (Vertical Scaling):** Cambiar la cajera por una con 4 brazos y procesador más rápido. Sigue siendo UNA caja, pero más potente.
- **Escalado horizontal (Horizontal Scaling):** Poner 4 cajas registradoras. Cada una atiende a una parte de los clientes.

**Vyntra usa escalado horizontal** — muchas copias del mismo servidor, no una más grande.

### ¿Qué se escala horizontalmente?

| Componente | ¿Escala horizontal? | Cómo |
|---|---|---|
| **Octane (HTTP)** | ✅ Sí | N procesos Octane detrás de un load balancer |
| **Reverb (WebSocket)** | ✅ Sí | N nodos Reverb + Redis Pub/Sub |
| **PostgreSQL (reads)** | ✅ Sí | Múltiples read replicas |
| **PostgreSQL (writes)** | ❌ No | Un solo primary (por ahora) |
| **Redis** | ✅ Sí | Redis Cluster si hace falta |

### Escalado horizontal vs microservicios

> **Este es el error más común entre juniors.** Confunden escalado horizontal del monolito con microservicios. No son lo mismo.

| Característica | Monolito Horizontal | Microservicios |
|---|---|---|
| Código | **Una sola app.** Misma base de código en cada nodo | Múltiples apps independientes |
| BD | **Una sola BD** (con replicas) | Cada servicio tiene su BD |
| Despliegue | Misma imagen Docker en todos los nodos | Despliegues independientes |
| Complejidad operativa | Baja | Alta (service mesh, etc.) |
| Debugging | Fácil (un solo log) | Difícil (logs distribuidos) |
| Consistencia de datos | Inmediata (misma BD) | Eventual (eventos) |
| ¿Para Vyntra? | ✅ Sí | ❌ No hay evidencia de necesidad |

**Vyntra horizontal scale** = 2-4 copias del mismo Laravel, todas conectadas a la misma BD (primary + replicas), con Redis compartido. No microservicios.

---

## 📚 ¿Por qué Redis NO es un write buffer?

### El mito

Alguien te dijo: "Redis es súper rápido, escribe todo en Redis y después pasa a Postgres". Suena bien, pero:

### El problema

1. **Redis es en memoria.** Si se cae, pierdes los datos no persistidos (aún con AOF/RDB, hay ventana de pérdida).
2. **Redis no tiene joins.** No puedes hacer `SELECT * FROM users JOIN posts ON users.id = posts.user_id WHERE...` en Redis.
3. **Redis no tiene ACID.** Las transacciones de Redis no son como las de Postgres. No hay rollback mágico.
4. **Complejidad enorme.** Ahora tienes que sincronizar Redis → Postgres, manejar fallos de sincronización, reconciliar datos...

### ¿Para qué SÍ sirve Redis en Vynta?

| Uso | ¿Redis? | ¿Por qué? |
|---|---|---|
| Cache de queries pesadas | ✅ Sí | `Cache::remember('users.active', 3600, fn() => ...)` |
| Cola de jobs (Queue) | ✅ Sí | Redis como backend de `QUEUE_CONNECTION=redis` |
| Sesiones de usuario | ✅ Sí | `SESSION_DRIVER=redis` |
| Rate limiting | ✅ Sí | `throttle:60,1` usa Redis |
| WebSocket Pub/Sub | ✅ Sí | Reverb usa Redis para broadcast entre nodos |
| **Write buffer (escribir en Redis y luego pasar a Postgres)** | ❌ No | Riesgo de pérdida + complejidad innecesaria |

> [!NOTE]
> **Write-behind con Redis es para casos extremos** (millones de writes/minuto donde Postgres no da abasto). Vyntra no está en ese caso. Cuando llegues a ese punto, reconsideras. Mientras tanto, YAGNI.

---

## 📚 Diferencia entre monolito escalable vs microservicios

Como complemento a la sección anterior, esta tabla te ayudará a defender tu elección cuando alguien te pregunte "¿por qué no usan microservicios?".

| Situación | Monolito escalable | Microservicios |
|---|---|---|
| 1,000 usuarios | ✅ Una instancia basta | ❌ Ya necesitas Kubernetes |
| 10,000 usuarios | ✅ Pooler + replica | 🤷‍♂️ Sigue siendo complejo |
| 100,000 usuarios | ✅ 4 nodos + 3 replicas | ✅ Empieza a tener sentido |
| Bug hunting | ✅ `tail -f laravel.log` | ❌ Jaeger + 15 dashboards |
| Team de 3 devs | ✅ Productivo en 1 semana | ❌ 3 meses de infraestructura |
| Deuda técnica | ✅ Refactor fácil | ❌ Cambiar contrato entre servicios |

**La regla de oro:** Empieza con monolito. Escala horizontal cuando sea necesario. Solo migra a microservicios cuando el monolito **demuestre** que no puede más (no cuando "crees" que no podrá).

---

## 📚 Números realistas

Aquí van los números que debes tener en la cabeza. No son opiniones, son límites conocidos de cada tecnología.

| Componente | Límite práctico | Qué significa |
|---|---|---|
| PostgreSQL sin pooler | ~1,000 conexiones | Postgres dedica ~2-5 MB por conexión inactiva. Con 1,000 conexiones, usa 2-5 GB solo en mantenerlas abiertas. |
| PostgreSQL con PgBouncer | ~25,000 conexiones (pool 25) | PgBouncer maneja la cola de espera. Postgres solo ve 25 conexiones activas. |
| PostgreSQL (1 primary + 1 replica) | ~50,000 reads/s + ~5,000 writes/s | La replica absorbe ~70% del tráfico de lectura. |
| PostgreSQL (1 primary + 3 replicas) | ~150,000 reads/s + ~5,000 writes/s | Más replicas = más reads. Writes no mejoran porque solo escribe el primary. |
| Redis single instance | ~100,000 ops/s | Suficiente para cache + queue + sesiones + Pub/Sub. |
| Octane (1 worker) | ~2,000 req/s (Laravel) | Depende de la complejidad de cada request. |
| Octane (4 workers) | ~8,000 req/s | Casi lineal mientras no haya contención en BD. |
| Reverb (1 nodo) | ~10,000 conexiones WS | Límite práctico por nodo. Con 3 nodos → 30,000. |

### ¿Qué significa esto para Vyntra?

```
Target: 30,000 WS + 60,000 HTTP/s en burst
                               ↓
Conexiones a Postgres: ~25,000 (PgBouncer pool=25)
Reads: ~42,000/s (70% de 60K HTTP) → 1 replica alcanza
Writes: ~18,000/s (30% de 60K HTTP) → Primary procesa
Octane workers: ~30 workers (en 4 nodos)
Reverb nodos: ~3 nodos (10K WS c/u)
```

> [!IMPORTANT]
> Estos son números **teóricos**. En producción real, los límites pueden ser 30-50% menores por latencia de red, complejidad de queries, y contención de recursos. Siempre prueba con carga real antes de confiar ciegamente.

---

## 📚 ¿Qué aprendiste en este archivo?

1. **Connection Pooler (PgBouncer)** = Un recepcionista que maneja las colas para que Postgres no se ahogue con 1,000 conexiones directas.
2. **Read Replica** = Una copia de solo lectura de tu BD. Postgres la mantiene sincronizada via streaming replication (WAL).
3. **Escalado horizontal** = Muchas copias pequeñas del servidor, no una más grande. Aplica a Octane, Reverb y replicas de BD.
4. **Redis NO es un write buffer** en Vyntra. Redis es para cache, colas, sesiones y Pub/Sub. No para reemplazar Postgres.
5. **Monolito escalable ≠ microservicios.** Vyntra escala horizontalmente el mismo código, no parte la app en servicios.
6. **Números reales:** Sin pooler ~1K conexiones, con pooler ~25K, con replica ~50K reads/s.

**En el próximo archivo (01_pgbouncer_setup.md)** vas a instalar PgBouncer en Docker y conectar Laravel a través de él. Manos a la obra.
