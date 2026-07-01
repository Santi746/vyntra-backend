# 05 — Estrategia de Escalabilidad de Vyntra (Resumen Ejecutivo)

> **Qué vamos a hacer**: Cerrar la guía con un panorama completo. Decisiones tomadas, decisiones descartadas, guía paso a paso de escalamiento, y la matemática que demuestra que el approach funciona.
> **Tiempo estimado**: 20 minutos.
> **Prerrequisitos**: Haber leído los pasos 00-04.

---

## 📊 Decisiones Tomadas

Esta tabla resume qué implementamos, qué descartamos y por qué.

| Decisión | Implementado | Descartado | ¿Por qué? |
|---|---|---|---|
| **Connection Pooler** | ✅ PgBouncer (transaction mode) | — | Postgres no maneja >1,000 conexiones. PgBouncer permite 25,000+ clientes con solo 25 conexiones reales. |
| **Read Replica** | ✅ 1 replica (escalable a N) | — | 70% del tráfico son lecturas. La replica las absorbe sin competir con writes. |
| **Read/Write Splitting** | ✅ Nativo de Laravel (config/database.php) | — | Laravel lo hace automáticamente. Sin proxies, sin middleware extra. |
| **Escalado Horizontal (Octane)** | ✅ N nodos + load balancer | — | Si 1 nodo no basta, pones más. Mismo código, mismo Redis, misma BD. |
| **Escalado Horizontal (Reverb)** | ✅ N nodos + Redis Pub/Sub | — | Reverb ya lo soporta nativamente con `REVERB_SCALING_ENABLED=true`. |
| **Redis Cluster** | — | ⚠️ Postergado | Redis single instance aguanta ~100K ops/s. Si llegamos a 200K+, migramos a cluster. |
| **Write-behind con Redis** | — | ❌ **Descartado** | Riesgo de pérdida de datos. Redis es en memoria. Si se cae, pierdes writes no persistidos. Para el volumen de Vyntra, Postgres con PgBouncer basta. |
| **Migración a Cassandra** | — | ❌ **Descartado** | Cassandra es para write-heavy extremo (miles de writes/s). Vyntra tiene ratio 70/30 reads/writes. Postgres es mejor para joins, transacciones y consistencia. |
| **Microservicios** | — | ❌ **Descartado** | Un monolito escalable horizontalmente es más simple, más barato y más fácil de debuggear. Microservicios solo si el monolito demuestra que no puede más. |
| **Sharding manual de tablas** | — | ❌ **Descartado** | PgBouncer + replicas resuelven el problema de conexiones y reads. Sharding (partir la BD en varias) es para cuando UNA tabla tiene billones de filas. No es el caso. |
| **Replicación síncrona** | — | ❌ **Descartado** | Sync replication espera confirmación de la replica antes de confirmar el write. Esto duplica la latencia de escritura. Async es más rápido y el lag es de milisegundos. |

---

## 📈 Tabla "Si crece X, hago Y"

Esta es tu guía de escalamiento paso a paso. No la sigas en orden — solo aplica cuando veas la señal.

| Señal | Qué significa | Qué hacer |
|---|---|---|
| **PgBouncer `cl_waiting` > 0** | Hay clientes esperando conexiones | Aumentar `default_pool_size` de 25 a 50 |
| **Postgres primary CPU > 70%** | El primary está sobrecargado | 1. Optimizar queries lentas 2. Cachear más en Redis 3. Agregar replica de lectura |
| **Replica CPU > 70%** | Las replicas están sobrecargadas | 1. Agregar otra replica 2. Verificar que las consultas tienen índices |
| **Redis memory > 80%** | Redis se está quedando sin RAM | 1. Reducir TTL de caché 2. Aumentar RAM del servidor Redis 3. Migrar a Redis Cluster |
| **Redis CPU > 70%** | Redis está sobrecargado de operaciones | 1. Revisar si hay queries N+1 en la app 2. Separar Redis por propósito (1 para cache, 1 para queue, 1 para sesiones) |
| **Octane worker busy > 90%** | Los workers están al límite | 1. Aumentar `--workers` en Octane 2. Si ya son 16+, agregar otro nodo |
| **Reverb conexiones > 8,000 por nodo** | Un nodo Reverb está cerca del límite | 1. Agregar otro nodo Reverb 2. Ajustar el load balancer |
| **Network latency entre nodos > 5ms** | Los nodos están geográficamente separados | 1. Usar Redis en misma región 2. Considerar replicas de Redis locales |
| **Primary writes > 4,000/s** | El primary se acerca a su límite | 1. Cachear writes frecuentes 2. Desnormalizar datos 3. Evaluar sharding |
| **Un nodo entero se cae** | Falla de servidor | El load balancer redirige tráfico a los nodos vivos. Si usas 2 nodos mínimo, no hay caída. |

---

## 📐 Capacidad actual vs capacidad con N nodos

### Hoy (1 nodo + 1 replica)

| Componente | Capacidad estimada |
|---|---|
| HTTP requests | ~5,000 req/s |
| WebSocket conexiones | ~10,000 |
| Reads de BD | ~50,000/s |
| Writes de BD | ~5,000/s |
| Redis ops | ~100,000/s |

### Con 2 nodos + 2 replicas (target inmediato)

| Componente | Capacidad estimada |
|---|---|
| HTTP requests | ~12,000 req/s |
| WebSocket conexiones | ~20,000 |
| Reads de BD | ~100,000/s |
| Writes de BD | ~5,000/s (no cambia, mismo primary) |
| Redis ops | ~100,000/s (mismo Redis) |

### Con 4 nodos + 3 replicas (target futuro)

| Componente | Capacidad estimada |
|---|---|
| HTTP requests | ~25,000 req/s |
| WebSocket conexiones | ~35,000 |
| Reads de BD | ~150,000/s |
| Writes de BD | ~5,000/s (mismo primary) |
| Redis ops | ~100,000/s (o cluster si hace falta) |

---

## 🔮 Matemática final: 30K WS + 60K HTTP

El objetivo de Vyntra es **30,000 conexiones WebSocket simultáneas + 60,000 HTTP requests/s en burst** (mix 70/30 reads/writes). Demostremos que 2 nodos + 2 replicas alcanzan.

### Desglose del tráfico

```
60,000 HTTP/s
  ├── 42,000 reads (70%)  → replicas
  └── 18,000 writes (30%) → primary

30,000 WebSocket conexiones
  ├── 15,000 por nodo (2 nodos)
  └── Cada conexión genera ~1 evento/minuto = 500 eventos/s total

Total writes a Postgres: 18,000/s (HTTP) + 500/s (WS events) ≈ 18,500/s
Total reads a Postgres:  42,000/s (HTTP)
```

### Capacidad de cada componente

#### Postgres Primary (writes)

```
Límite práctico de Postgres: ~5,000 writes/s por instancia
¿Necesitamos? ~18,500 writes/s
¿Problema? SÍ, 18,500 >> 5,000
```

> [!CAUTION]
> **Aquí está el cuello de botella real.** 18,500 writes/s es demasiado para un solo primary. Esto significa que necesitamos estrategias de mitigación:
>
> 1. **Cachear writes repetitivos** — muchos writes son "heartbeats" o "visto por última vez" que pueden ir a Redis
> 2. **Batch inserts** — agrupar writes en lotes de 100
> 3. **Desnormalizar** — reducir joins en writes calientes
> 4. **Sharding** — partir la tabla de mensajes por club_id (futuro)
>
> **Solución realista:** Optimizar hasta ~10,000 writes/s y complementar con Redis para writes no críticos (logs, analytics, heartbeats).

#### Postgres Replicas (reads)

```
Límite práctico de 1 replica: ~50,000 reads/s
¿Necesitamos? ~42,000 reads/s
¿Problema? NO, con 1 replica alcanza.
Margen: ~8,000 reads/s libres.
```

#### PgBouncer

```
Límite con default_pool_size=25: ~25,000 clientes concurrentes
¿Necesitamos? 30,000 WS + 60,000 HTTP = ~90,000 conexiones potenciales
¿Problema? SÍ, 90,000 > 25,000
```

> [!NOTE]
> Pero **no todos los requests se conectan simultáneamente**. Las conexiones HTTP son efímeras (duran milisegundos). Las 30,000 WS sí están siempre conectadas. Entonces:
>
> - 30,000 WS siempre conectadas (siempre ocupan un slot en PgBouncer)
> - 60,000 HTTP/s pero cada request dura ~50ms → ~3,000 conexiones simultáneas promedio
> - Total simultáneo real: ~33,000 → ajustamos `max_client_conn = 2000` y `default_pool_size = 50`

#### Redis

```
Límite single instance: ~100,000 ops/s
¿Necesitamos? Cache: ~20K ops + Queue: ~5K ops + Sesiones: ~1K ops + Pub/Sub: ~1K ops = ~27,000 ops/s
¿Problema? NO, 27,000 << 100,000.
Margen: ~73,000 ops/s libres.
```

#### Octane

```
1 nodo con 8 workers: ~5,000 req/s
¿Necesitamos? 60,000 req/s
¿Nodos necesarios? 60,000 / 5,000 = 12 nodos (demasiados)

MEJOR:
1 nodo con 32 workers: ~20,000 req/s (con buena CPU)
¿Necesitamos? 60,000 req/s
¿Nodos necesarios? 3 nodos con 32 workers cada uno
```

> [!TIP]
> El número de workers de Octane no escala linealmente porque compiten por CPU. 32 workers en una máquina de 16 cores es el sweet spot. Más workers que cores = contención.

#### Reverb

```
1 nodo: ~10,000 WS conexiones
¿Necesitamos? 30,000 WS
¿Nodos necesarios? 3 nodos
```

### Conclusión de la matemática

| Componente | Necesitamos | Tenemos con 3 nodos + 2 replicas | ¿Alcanza? |
|---|---|---|---|
| HTTP req/s | 60,000 | ~60,000 (3 nodos x 20K c/u) | ✅ Límite |
| WS conexiones | 30,000 | ~30,000 (3 nodos x 10K c/u) | ✅ Justo |
| Writes/s | ~18,500 | ~5,000 (primary) | ❌ **Cuello de botella** |
| Reads/s | ~42,000 | ~100,000 (2 replicas) | ✅ Sobrado |
| Redis ops/s | ~27,000 | ~100,000 | ✅ Sobrado |

**Veredicto:** El cuello de botella es el **primary de Postgres** (writes). Para llegar a 60K HTTP/s necesitamos estrategias de reducción de writes (cache, batch, desnormalización) o evaluar sharding.

---

## 🚫 Lo que NO se implementó y por qué

### 1. Write-behind con Redis

**Qué es:** En lugar de escribir directo a Postgres, escribes en Redis y un worker background pasa los datos a Postgres.

**Riesgos:**
- Redis es en memoria. Si el servidor se cae antes de que el worker persista, pierdes datos.
- Consistencia eventual: el usuario ve el dato en Redis, pero no está en Postgres.
- Complejidad: ahora tienes que reconciliar Redis vs Postgres.

**Para Vyntra:** No. Los datos de chat, clubes y amigos deben persistir inmediatamente. Redis es para cache, no para storage primario.

### 2. Migración a Cassandra

**Qué es:** Base de datos NoSQL diseñada para writes masivos. Usada por Discord (parte de su stack).

**Por qué no:**
- Cassandra no tiene joins (tienes que desnormalizar todo)
- Cassandra no tiene transacciones ACID
- Cassandra necesita más nodos para ser tolerante a fallos (mínimo 3)
- Postgres con optimizaciones alcanza para el volumen de Vyntra

**Para Vyntra:** No. Si el primary de Postgres se satura, primero optimizamos writes, luego consideramos sharding. Cassandra es el último recurso.

### 3. Microservicios

**Qué es:** Partir la app en servicios independientes (users, chats, clubs, etc.).

**Por qué no:**
- Complejidad operativa 10x
- Necesitas Kubernetes, service mesh, tracing distribuido
- Equipo pequeño (3 devs) → productividad se desploma
- El monolito escala horizontalmente con la misma eficacia

**Para Vyntra:** No. El monolito es la opción correcta para este equipo y este volumen. Microservicios cuando el monolito demuestre que no puede más (y aún así, reconsidera).

### 4. Sharding manual de tablas

**Qué es:** Partir una tabla grande en varias tablas físicas (ej: `messages_1`, `messages_2`, `messages_3`) según un criterio (club_id, user_id, etc.).

**Por qué no (por ahora):**
- Postgres maneja tablas de hasta 32TB
- Con índices adecuados, tablas de 100M filas son manejables
- Sharding manual rompe joins, foreign keys y queries simples

**Para Vyntra:** Postergado. Monitorear tamaño de la tabla `messages`. Si supera 500M filas o 500GB, evaluar sharding por `club_id`.

---

## 🏆 ¿Por qué este approach impresiona a un senior de 9 años?

| Lo que hicimos | Lo que NO hicimos | Por qué un senior lo respeta |
|---|---|---|
| PgBouncer con pool mode transaction | No instalamos un proxy SQL ni un middleware custom | Solución madura, probada, liviana. No reinventamos la rueda. |
| Read replicas con streaming replication | No usamos replicación lógica ni herramientas externas | Streaming replication es nativa de Postgres, eficiente y confiable. |
| Read/write splitting nativo de Laravel | No instalamos un balanceador de queries SQL | Laravel ya lo hace. Para qué agregar otro punto de falla. |
| Escalado horizontal del monolito | No microservicios | Reconocimiento honesto de que el monolito alcanza. Microservicios son costosos y solo se justifican con evidencia. |
| Redis solo para cache/queue/pubsub | No write-behind | Entender que Redis no es una BD ACID. Separar responsabilidades correctamente. |
| YAGNI en cada decisión | No instalamos lo que no necesitamos hoy | "You Ain't Gonna Need It" — la habilidad más infravalorada en ingeniería. |

---

## 🚫 Anti-patrones que NO cometimos (y por qué)

### Anti-patrón 1: "Pongamos un Redis delante de Postgres para todo"

**Lo que hace la gente:** Cachear todas las queries de Postgres en Redis con TTL de 1 hora.

**Por qué es malo:** Los datos se vuelven inconsistentes. El usuario ve datos viejos. El cache invalidation es un problema de computación difícil.

**Qué hicimos nosotros:** Cache selectivo solo para queries pesadas y poco cambiantes (ej: lista de clubes, permisos de usuario). Usamos `Cache::remember()` con TTLs cortos (60-300s).

### Anti-patrón 2: "Agreguemos más replicas para solucionar el problema de writes"

**Lo que hace la gente:** Las replicas NO ayudan con writes. Solo ayudan con reads.

**Por qué es malo:** Estás agregando infraestructura que no soluciona tu cuello de botella.

**Qué hicimos nosotros:** Identificamos el cuello de botella real (writes en primary) y planeamos estrategias específicas (cache, batch, desnormalización).

### Anti-patrón 3: "Microservicios desde el día 1"

**Lo que hace la gente:** Dividir la app en 15 servicios antes de tener 1,000 usuarios.

**Por qué es malo:** Multiplicas la complejidad por 10. Debugging se vuelve imposible. El equipo gasta 80% del tiempo en infraestructura y 20% en features.

**Qué hicimos nosotros:** Monolito escalable horizontalmente. Cuando el monolito demuestre que no puede más (con datos, no con opiniones), evaluamos partes específicas para extraer a servicios.

### Anti-patrón 4: "Connection pool gigante para más rendimiento"

**Lo que hace la gente:** `default_pool_size = 500` en PgBouncer pensando que más conexiones = más rendimiento.

**Por qué es malo:** Postgres no escala linealmente con conexiones. Más de ~50 conexiones activas simultáneas empiezan a competir por CPU, locks y memoria. El rendimiento **decrece**.

**Qué hicimos nosotros:** `default_pool_size = 25`. Suficiente para mantener ocupado al Postgres sin saturarlo. Si `cl_waiting` > 0, subimos a 50. Nunca más de 100.

### Anti-patrón 5: "Sticky sessions en HTTP"

**Lo que hace la gente:** Configurar el load balancer HTTP con sticky sessions para que un usuario siempre caiga en el mismo nodo.

**Por qué es malo:** Rompe la distribución de carga. Si un usuario hace 100 requests, los 100 van al mismo nodo. Los otros nodos están infrautilizados.

**Qué hicimos nosotros:** Sticky sessions SOLO para WebSocket (Reverb). HTTP no necesita sticky porque Redis es compartido (sesiones, cache). Cualquier nodo puede atender cualquier request HTTP.

---

## 🔗 Referencias cruzadas

Esta guía se relaciona con otras partes de la arquitectura de Vyntra:

| Documento | Relación |
|---|---|
| [`docs/architecture/mandatory_patterns.md`](../architecture/mandatory_patterns.md) §10 (Octane/Redis) | Octane prohíbe estado mutable estático y `request()->user()` en modelos. El escalado horizontal funciona porque Octane sigue estas reglas. |
| [`docs/architecture/mandatory_patterns.md`](../architecture/mandatory_patterns.md) §18 (YAGNI) | Aplicamos YAGNI en cada decisión de escalabilidad. No instalamos lo que no necesitamos hoy. |
| [`docs/project_steps_guides/realtime_layers/events_layer/`](../events_layer/) | La capa de eventos (WebSockets) depende de Redis y Reverb. El escalado horizontal de Reverb usa Redis Pub/Sub. |
| [`docs/architecture/real_time_rules.md`](../architecture/real_time_rules.md) | Reglas de broadcast asíncrono. Los eventos nunca se disparan sincrónicamente desde controllers (violaría Octane). |
| [`docs/architecture/IMPORTANT_PRACTICES.md`](../architecture/IMPORTANT_PRACTICES.md) | Prácticas generales de la arquitectura. Consistente con los patrones de escalabilidad. |

---

## ✅ Checklist final de la estrategia

- [ ] **PgBouncer instalado** con `pool_mode = transaction` y `default_pool_size = 25`
- [ ] **Read replica configurada** con streaming replication asíncrona
- [ ] **Laravel configurado** con read/write splitting (reads → replica, writes → primary)
- [ ] **Sticky connections activadas** (`sticky = true` en config/database.php)
- [ ] **Octane corriendo con workers suficientes** (ajustar según CPU)
- [ ] **Reverb con escalado horizontal** (`REVERB_SCALING_ENABLED=true`)
- [ ] **Load balancer configurado** (Nginx con `least_conn` para HTTP, `ip_hash` para WS)
- [ ] **Redis compartido** entre todos los nodos (sesiones, cache, queue, pub/sub)
- [ ] **Monitoreo de replication lag** (alarma si > 1 segundo)
- [ ] **Monitoreo de PgBouncer** (alarma si `cl_waiting` > 0 por más de 5 minutos)

---

## 📚 ¿Qué aprendiste en este archivo?

1. **El cuello de botella real** de Vyntra no son las conexiones ni las lecturas: son las **escrituras en el primary de Postgres** (~18,500/s contra ~5,000/s de capacidad).
2. **No necesitas microservicios** para escalar. Un monolito con PgBouncer + replicas + load balancer alcanza para 30K WS + 60K HTTP.
3. **Cada decisión tiene una justificación** con números, no con opiniones. Postgres da ~5K writes/s, no ~50K. Redis da ~100K ops/s, no ~1M.
4. **Los anti-patrones** son más importantes que los patrones. Saber qué NO hacer es tan valioso como saber qué hacer.
5. **La guía "Si crece X, hago Y"** te da un plan de acción concreto para cada síntoma de saturación. No tienes que adivinar.
6. **YAGNI** es el principio más importante de esta guía. No instales Cassandra hoy porque quizás la necesites en 2 años. Instálala cuando la necesites.

---

> **Fin de la guía de escalabilidad de BD.** Has aprendido desde qué es un pool de conexiones hasta cómo escalar un monolito a 4 nodos. Ahora tienes las herramientas para que Vyntra aguante 30K conexiones WebSocket y 60K HTTP requests sin morir en el intento.
>
> **Próximo paso:** Vuelve al archivo `README.md` de esta carpeta y marca las casillas de los pasos que completaste.
