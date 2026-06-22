# 08 — Testing y Benchmarks de Carga

> **Propósito**: Guia completa de testing funcional y de carga para la capa de eventos en tiempo real de Vyntra. Disenada para validar que la infraestructura WebSocket soporta desde 1 hasta 30,000 conexiones simultaneas.
> **Stack**: Laravel 13 + Reverb + Redis + Octane + Horizon + k6.
> **Audiencia**: Desarrolladores backend, DevOps, evaluadores tecnicos senior.
> **Estado**: Documento de referencia. Implementar DESPUES de WebSocket funcionando.

---

## 0. Prerrequisitos

Antes de ejecutar cualquier prueba de carga, verifica que:

- [ ] Reverb esta corriendo (`php artisan reverb:status`).
- [ ] Worker de cola `broadcasts` esta activo y sin jobs fallidos.
- [ ] Octane esta instalado (se recomienda para pruebas de alta carga).
- [ ] Horizon esta instalado para monitorear colas durante las pruebas.
- [ ] Redis responde (`redis-cli ping` -> `PONG`).
- [ ] Las rutas de autenticacion Sanctum funcionan correctamente.

> **Nota**: Horizon y Pulse se documentan en `07_observability_horizon_pulse.md` y deben implementarse antes de estas pruebas para tener visibilidad del sistema bajo carga.

---

## 1. Herramientas de Testing

### 1.1 k6 (Recomendado Principal)

**Sitio oficial**: https://k6.io
**Tipo**: Open-source, scripting en JavaScript, CLI y dashboard en la nube.

**Ventajas para Vyntra**:

- Soporte nativo para WebSocket mediante el modulo `k6/ws` (experimental) y `k6/net/grpc`.
- Scripts en JavaScript (baja barrera de entrada para el equipo).
- Metricas integradas: latencia, throughput, VUs, errores.
- Exportacion a JSON, CSV, InfluxDB, Prometheus.
- Sin dependencias externas (binario unico).

**Limitaciones**:

- El modulo WebSocket (`k6/ws`) es experimental y no soporta el protocolo Pusher completo. Para pruebas completamente funcionales con Reverb se necesita un adaptador, pero para pruebas de conexion/carga basica es suficiente.
- No soporta reconexion automatica ni manejo de estados complejos.

### 1.2 Artillery

**Sitio oficial**: https://artillery.io
**Tipo**: Open-source, Node.js, YAML/JS config.

**Ventajas**:

- Soporte nativo para WebSocket via plugin `artillery-engine-ws`.
- Configuracion en YAML (declarativa, facil de leer).
- Reportes HTML con graficos.
- Soportado por la comunidad.

**Ejemplo de configuracion para WebSocket**:

```yaml
# artillery-websocket.yml
config:
  target: "ws://localhost:8080"
  phases:
    - duration: 60
      arrivalRate: 10
      rampTo: 100
      name: "Rampa de carga"
  engines:
    ws: {}
  ws:
    path: "/app/vyntra-key"
scenarios:
  - engine: "ws"
    flow:
      - send: '{"event": "pusher:subscribe", "data": {"channel": "private-user.test-uuid"}}'
      - think: 5
      - send: '{"event": "pusher:ping"}'
```

### 1.3 Postman WebSocket

**Tipo**: GUI, testing funcional manual.

**Uso**:

1. Abrir Postman.
2. Nueva peticion -> pestana WebSocket.
3. URL: `ws://localhost:8080/app/vyntra-key`.
4. Conectar.
5. Enviar frames JSON manualmente.
6. Verificar respuestas en la interfaz.

**Ideal para**: Testing funcional rapido, debug de payloads, verificacion manual de formato `{t, d}`.

### 1.4 wscat

**Sitio oficial**: https://github.com/websockets/wscat
**Tipo**: CLI, Node.js.

**Uso**:

```bash
# Conectar a Reverb
npx wscat -c ws://localhost:8080/app/vyntra-key

# Suscribirse manualmente a un canal
> {"event": "pusher:subscribe", "data": {"channel": "private-user.test-uuid"}}
```

**Ideal para**: Testing rapido de conectividad, verificar que Reverb acepta conexiones.

---

## 2. Escenarios de Prueba (Priorizados)

### 2.1 Escenario 1 — Smoke Test

**Proposito**: Verificar que el WebSocket funciona en condiciones minimas.

| Parametro | Valor |
|-----------|-------|
| VUs | 1 |
| Mensajes por VU | 1 |
| Duracion | 10s |
| Criterio exito | Conexion exitosa, mensaje recibido, latencia < 1s |

**Script k6 basico**:

```javascript
// smoke-test.js
import { check } from 'k6';
import ws from 'k6/ws';

export const options = {
  vus: 1,
  duration: '10s',
  thresholds: {
    ws_connecting_duration: ['p(95) < 1000'],
    ws_sessions: ['count == 1'],
  },
};

export default function () {
  const url = `ws://localhost:8080/app/vyntra-key`;
  const params = { tags: { type: 'smoke' } };

  const res = ws.connect(url, params, function (socket) {
    socket.on('open', () => {
      console.log('Conectado exitosamente');

      // Suscribirse a canal privado
      socket.send(JSON.stringify({
        event: 'pusher:subscribe',
        data: { channel: 'private-user.test-uuid' },
      }));
    });

    socket.on('message', (data) => {
      const parsed = JSON.parse(data);
      console.log(`Mensaje recibido: ${data}`);

      check(parsed, {
        'Payload tiene formato valido': (p) => p.data && p.data.t && p.data.d,
        'Evento es tipo esperado': (p) => ['MESSAGE_CREATE', 'NOTIFICATION_CREATE', 'FRIENDSHIP_STATUS_CHANGED'].includes(p.data.t),
      });

      socket.close();
    });

    socket.on('error', (e) => {
      console.error(`Error: ${e}`);
    });

    socket.setTimeout(() => {
      console.log('Timeout - cerrando conexion');
      socket.close();
    }, 5000);
  });

  check(res, {
    'Conexion establecida': (r) => r && r.status === 101,
  });
}
```

**Ejecucion**:

```bash
k6 run smoke-test.js
```

### 2.2 Escenario 2 — Carga Media

**Proposito**: Simular un club activo con usuarios enviando mensajes.

| Parametro | Valor |
|-----------|-------|
| VUs | 100 |
| Mensajes por VU | 10 |
| Duracion | 2 min |
| Criterio exito | 0 errores, latencia P95 < 300ms |

**Script k6**:

```javascript
// medium-load.js
import { check, sleep } from 'k6';
import ws from 'k6/ws';

export const options = {
  stages: [
    { duration: '30s', target: 100 },  // Rampa de subida
    { duration: '1m', target: 100 },   // Carga sostenida
    { duration: '30s', target: 0 },    // Rampa de bajada
  ],
  thresholds: {
    ws_connecting_duration: ['p(95) < 300'],
    ws_sessions: ['count > 90'],      // Al menos 90% conexiones exitosas
    http_req_failed: ['rate < 0.01'], // < 1% errores
  },
};

export default function () {
  const url = `ws://localhost:8080/app/vyntra-key`;

  ws.connect(url, null, function (socket) {
    socket.on('open', () => {
      // Suscribirse a canal de club
      const channelUuid = `club-${__VU % 10}`;
      socket.send(JSON.stringify({
        event: 'pusher:subscribe',
        data: { channel: `private-club.${channelUuid}` },
      }));

      // Enviar N mensajes
      for (let i = 0; i < 10; i++) {
        socket.send(JSON.stringify({
          event: 'client-message',
          data: {
            content: `Mensaje de prueba VU ${__VU} mensaje ${i}`,
            client_uuid: `${__VU}-${i}-${Date.now()}`,
          },
        }));
        sleep(0.5);
      }
    });

    socket.on('message', (data) => {
      check(JSON.parse(data), {
        'Mensaje recibido': () => true,
      });
    });

    socket.on('close', () => console.log(`VU ${__VU} desconectado`));
    socket.on('error', (e) => console.error(`VU ${__VU} error: ${e}`));
  });

  sleep(1);
}
```

**Ejecucion**:

```bash
k6 run medium-load.js --out json=results/medium-load.json
```

### 2.3 Escenario 3 — Carga Alta

**Proposito**: Simular multitud de clubes activos simultaneamente.

| Parametro | Valor |
|-----------|-------|
| VUs | 1,000 |
| Mensajes por VU | 100 |
| Duracion | 5 min |
| Criterio exito | Latencia P99 < 500ms, 0 perdida de mensajes |

**Consideraciones para este escenario**:

- Asegurate de que Octane esta activo antes de ejecutar.
- Monitorea el uso de Redis con `redis-cli INFO`.
- Verifica que Horizon no muestra acumulacion de jobs en cola `broadcasts`.
- Si el sistema falla, documenta el punto exacto de quiebre.

**Ejecucion**:

```bash
k6 run high-load.js --out csv=results/high-load.csv
```

### 2.4 Escenario 4 — Estrés (Punto de Quiebre)

**Proposito**: Encontrar el limite del sistema.

| Parametro | Valor |
|-----------|-------|
| VUs | Incrementar hasta fallo |
| Mensajes por VU | 50 |
| Duracion | Hasta fallo del sistema |
| Criterio exito | Documentar el punto exacto de quiebre |

**Script k6**:

```javascript
// stress-test.js
import ws from 'k6/ws';

export const options = {
  stages: [
    { duration: '2m', target: 100 },    // 100 VUs
    { duration: '2m', target: 500 },    // 500 VUs
    { duration: '2m', target: 1000 },   // 1000 VUs
    { duration: '2m', target: 2000 },   // 2000 VUs
    { duration: '2m', target: 5000 },   // 5000 VUs
    { duration: '2m', target: 10000 },  // 10000 VUs
  ],
  thresholds: {
    ws_sessions: ['rate >= 0.95'],     // < 95% conexiones = punto de quiebre
  },
};

export default function () {
  const url = `ws://localhost:8080/app/vyntra-key`;

  ws.connect(url, null, function (socket) {
    socket.on('open', () => {
      socket.send(JSON.stringify({
        event: 'pusher:subscribe',
        data: { channel: `private-user.vu-${__VU}` },
      }));
    });

    socket.on('message', () => { /* Solo mantener conexion */ });

    socket.on('error', () => {
      // Registrar el VU y momento del error
      console.error(`FALLO en VU ${__VU} a los ${__ITER} segundos`);
    });
  });
}
```

**Ejecucion**:

```bash
k6 run stress-test.js 2>&1 | tee results/stress-test.log
```

### 2.5 Escenario 5 — Pico (Evento Masivo)

**Proposito**: Simular un evento masivo donde 10,000 usuarios se conectan simultaneamente y envian 1 mensaje cada uno.

| Parametro | Valor |
|-----------|-------|
| VUs | 10,000 |
| Mensajes por VU | 1 |
| Duracion | 30s (todos se conectan al mismo tiempo) |
| Criterio exito | Sistema no colapsa, latencia P99 < 2s |

**Ejecucion**:

```bash
k6 run spike-test.js --vus 10000 --duration 30s
```

---

## 3. Metricas a Medir

### 3.1 Definiciones

| Metrica | Unidad | Como se mide | Instrumento |
|---------|--------|-------------|-------------|
| Latencia P50 | ms | Mediana del tiempo entre envio y recepcion | k6 `ws_connecting_duration` |
| Latencia P95 | ms | 95% de los mensajes se entregan en este tiempo | k6 `http_req_duration` |
| Latencia P99 | ms | 99% de los mensajes se entregan en este tiempo | k6 `http_req_duration` |
| Throughput | msg/s | Mensajes entregados por segundo | k6 `iterations` / tiempo |
| Conexiones activas | count | WebSockets abiertos simultaneamente | redis-cli `CLIENT LIST` |
| Jobs en cola `broadcasts` | count | Pendientes vs procesados | Horizon dashboard |
| Memoria Redis | MB | Memoria usada por Redis | redis-cli `INFO memory` |
| Tasa de error | % | Conexiones fallidas / total | k6 `http_req_failed` |

### 3.2 Tabla de Resultados Esperados

| Escenario | VUs | P50 | P95 | P99 | Throughput | Tasa error |
|-----------|-----|-----|-----|-----|------------|------------|
| Smoke | 1 | < 50ms | < 100ms | < 200ms | > 10 msg/s | 0% |
| Carga Media | 100 | < 100ms | < 200ms | < 300ms | > 100 msg/s | < 1% |
| Carga Alta | 1,000 | < 200ms | < 300ms | < 500ms | > 500 msg/s | < 2% |
| Estres | Variable | < 500ms | < 1s | < 2s | Maximo sostenible | Documentar |
| Pico | 10,000 | < 500ms | < 1s | < 2s | > 1000 msg/s | < 5% |

---

## 4. Como Ejecutar Cada Prueba

### 4.1 Instalacion de k6

```bash
# Windows (PowerShell - winget)
winget install k6

# macOS
brew install k6

# Linux (Debian/Ubuntu)
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Docker
docker pull grafana/k6
docker run -i grafana/k6 run - <script.js
```

### 4.2 Script base para k6 con WebSocket

```javascript
// websocket-base.js
import { check, sleep } from 'k6';
import ws from 'k6/ws';

// Configuracion
const REVERB_HOST = __ENV.REVERB_HOST || 'localhost';
const REVERB_PORT = __ENV.REVERB_PORT || '8080';
const REVERB_APP_KEY = __ENV.REVERB_APP_KEY || 'vyntra-key';
const WS_URL = `ws://${REVERB_HOST}:${REVERB_PORT}/app/${REVERB_APP_KEY}`;

export const options = {
  vus: 1,
  duration: '10s',
};

export default function () {
  const startTime = Date.now();

  const response = ws.connect(WS_URL, null, function (socket) {
    socket.on('open', function open() {
      const connectTime = Date.now() - startTime;
      console.log(`Conexion establecida en ${connectTime}ms`);

      // Suscribirse a canal privado
      socket.send(JSON.stringify({
        event: 'pusher:subscribe',
        data: { channel: 'private-user.test-uuid' },
      }));
    });

    socket.on('message', function incoming(data) {
      const latency = Date.now() - startTime;
      const parsed = JSON.parse(data);

      // Verificar formato {t, d}
      const hasValidFormat = parsed.data && parsed.data.t && parsed.data.d;

      check(parsed, {
        'Payload con formato {t, d}': () => hasValidFormat,
        'Latencia aceptable': () => latency < 5000,
      });

      if (hasValidFormat) {
        console.log(`Evento: ${parsed.data.t}, Latencia: ${latency}ms`);
      }
    });

    socket.on('error', function (e) {
      console.error(`Error WebSocket: ${JSON.stringify(e)}`);
      check(null, { 'Sin errores de conexion': () => false });
    });

    socket.on('close', function () {
      console.log('Conexion cerrada por el servidor');
    });

    socket.setTimeout(function () {
      console.log('Finalizando VU por timeout');
      socket.close();
    }, 10000);
  });

  check(response, {
    'Status HTTP 101 (Switching Protocols)': (r) => r.status === 101,
  });

  sleep(1);
}
```

**Ejecucion**:

```bash
# Configuracion basica
k6 run websocket-base.js

# Con variables de entorno
k6 run -e REVERB_HOST=192.168.1.100 -e REVERB_PORT=8080 websocket-base.js
```

### 4.3 Recoleccion de Resultados

```bash
# Salida en JSON
k6 run smoke-test.js --out json=results/smoke-test.json

# Salida en CSV
k6 run medium-load.js --out csv=results/medium-load.csv

# Salida en consola (summary)
k6 run high-load.js

# Con resumen detallado
k6 run stress-test.js --summary-export=results/stress-summary.json
```

### 4.4 Script de pruebas automatizadas (PowerShell)

```powershell
# run-all-tests.ps1
# Ejecuta todos los escenarios secuencialmente

$ResultsDir = "results"
New-Item -ItemType Directory -Force -Path $ResultsDir

Write-Host "=== Escenario 1: Smoke Test ==="
k6 run smoke-test.js --out json=$ResultsDir\smoke-test.json
if ($LASTEXITCODE -ne 0) { Write-Host "FALLO: Smoke test"; exit 1 }

Write-Host "=== Escenario 2: Carga Media ==="
k6 run medium-load.js --out json=$ResultsDir\medium-load.json
if ($LASTEXITCODE -ne 0) { Write-Host "FALLO: Carga media"; exit 1 }

Write-Host "=== Escenario 3: Carga Alta ==="
k6 run high-load.js --out json=$ResultsDir\high-load.json
if ($LASTEXITCODE -ne 0) { Write-Host "FALLO: Carga alta"; exit 1 }

Write-Host "=== Escenario 4: Estres ==="
k6 run stress-test.js --out json=$ResultsDir\stress-test.json
if ($LASTEXITCODE -ne 0) { Write-Host "FALLO: Test de estres"; exit 1 }

Write-Host "=== Todos los tests completados ==="
```

---

## 5. Interpretacion de Resultados

### 5.1 Valores de Referencia

| Metrica | Excelente | Bueno | Aceptable | Malo |
|---------|-----------|-------|-----------|------|
| P50 latencia | < 50ms | < 100ms | < 200ms | > 500ms |
| P95 latencia | < 100ms | < 200ms | < 500ms | > 1s |
| P99 latencia | < 200ms | < 500ms | < 1s | > 2s |
| Throughput (1k VUs) | > 1000 msg/s | > 500 msg/s | > 100 msg/s | < 50 msg/s |
| Conexiones simultaneas | > 10,000 | > 5,000 | > 1,000 | < 500 |
| Tasa error | 0% | < 1% | < 5% | > 5% |
| Jobs en cola (max) | < 10 | < 100 | < 1000 | > 5000 |

### 5.2 Que Hacer si...

| Sintoma | Causa probable | Donde mirar |
|---------|---------------|-------------|
| P99 > 1s en carga media | Worker saturado, pocos workers en cola `broadcasts` | Horizon dashboard |
| Conexiones fallidas > 5% | Reverb sin recursos, limite de conexiones Redis | `redis-cli CLIENT LIST`, `php artisan reverb:status` |
| Jobs acumulandose en cola | Workers lentos, demasiados jobs por segundo | Horizon -> cola `broadcasts` |
| Memoria Redis creciendo | Sin limite de memoria, eventos sin cleanup | `redis-cli INFO memory`, `CONFIG GET maxmemory` |
| Errores 500 en autenticacion | Policy mal configurada, token expirado | `storage/logs/laravel.log` |
| Latencia alta pero CPU baja | Cuello de botella de red, limite de conexiones SO | `netstat -s`, `sysctl net.core.somaxconn` |
| Reconexiones frecuentes | Timeout de Reverb, balanceador matando conexiones inactivas | `config/reverb.php` -> `apps[0].allowed_origins` |

### 5.3 Arbol de Decision para Diagnostico

```
Problema: Latencia alta o conexiones fallidas
|
├── Revisar Horizon
│   ├── Jobs acumulados? -> Aumentar workers en cola broadcasts
│   └── Jobs fallidos? -> Revisar logs del worker
|
├── Revisar Redis
│   ├── Memoria > 80%? -> Aumentar maxmemory o limpiar keys
│   └── Conexiones > 1000? -> Aumentar maxclients en redis.conf
|
├── Revisar Reverb
│   ├── Esta corriendo? -> php artisan reverb:status
│   └── Logs de error? -> storage/logs/laravel.log
|
└── Revisar OS
    ├── Socket backlog lleno? -> netstat -s | grep overflowed
    └── Limite de archivos abiertos? -> ulimit -n
```

---

## 6. Herramientas Complementarias

### 6.1 Laravel Pulse

> Documentado en detalle en `07_observability_horizon_pulse.md`.

Proporciona monitoreo en vivo del rendimiento del backend durante las pruebas:

- **Slow queries**: Detecta N+1 generados por la carga.
- **Endpoints lentos**: Rutas que degradan bajo estres.
- **Cache hits/misses**: Eficiencia del cache Redis.
- **Errores HTTP**: 500, 429 durante picos de carga.

**Uso durante pruebas**:

```bash
# Terminal 1: Iniciar pruebas de carga
k6 run high-load.js

# Terminal 2: Monitorear Pulse
# Abrir http://localhost:8000/pulse
```

### 6.2 Horizon Dashboard

> Documentado en detalle en `07_observability_horizon_pulse.md`.

Proporciona monitoreo de colas durante las pruebas:

- Jobs pendientes vs procesados en cola `broadcasts`.
- Tiempo de procesamiento promedio.
- Workers activos y su estado.

**Uso durante pruebas**:

```bash
# Terminal 1: Iniciar pruebas
k6 run stress-test.js

# Terminal 2: Monitorear Horizon
# Abrir http://localhost:8000/horizon

# O por CLI
php artisan horizon:status
php artisan horizon:metrics
```

### 6.3 Redis CLI

Comandos esenciales para debuggear Redis durante pruebas de carga:

```bash
# Informacion general del servidor Redis
redis-cli INFO

# Solo memoria
redis-cli INFO memory

# Solo estadisticas
redis-cli INFO stats

# Conexiones activas
redis-cli CLIENT LIST

# Monitorear comandos en tiempo real (CUIDADO: intensivo)
redis-cli MONITOR

# Ver cuantas keys por tipo
redis-cli INFO keyspace

# Ver tamano de la base de datos
redis-cli DBSIZE

# Limpiar cache (solo desarrollo)
redis-cli FLUSHDB
```

**Interpretacion de `INFO memory`**:

```
used_memory: 50MB        -> Memoria usada actualmente
used_memory_rss: 100MB   -> Memoria real en RAM
used_memory_peak: 200MB  -> Pico maximo alcanzado
maxmemory: 500MB         -> Limite configurado
maxmemory_policy: allkeys-lru -> Politica de expiracion
```

**Alarmas**:

- `used_memory` > 80% de `maxmemory` -> Riesgo de OOM (Out of Memory).
- `connected_clients` > `maxclients` - 100 -> Riesgo de rechazar conexiones.
- `keyspace_misses` > `keyspace_hits` -> Cache ineficiente.

### 6.4 php artisan reverb:status

```bash
php artisan reverb:status
# Output esperado:
# Reverb server is running.
#
# Si no corre:
# Reverb server is not running.
```

### 6.5 php artisan queue:status

```bash
php artisan queue:status
# Output esperado (si hay jobs):
# The queue connection [redis] has [42] jobs on the queue.

# Si todo esta procesado:
# The queue connection [redis] has [0] jobs on the queue.
```

### 6.6 Monitoreo de Sistema Operativo

```bash
# Linux: limite de archivos abiertos por proceso
ulimit -n

# Linux: conexiones socket en estado TIME_WAIT
ss -s

# Linux: estadisticas de red
netstat -s | grep -E "overflow|drop|retransmit"

# Windows: conexiones activas
netstat -an | findstr :8080

# Windows: contadores de rendimiento
Get-Counter "\WebSocket(*)\Current Connections"
```

---

## 7. Checklist Final de Testing

### Pruebas Funcionales Basicas

- [ ] **Smoke test**: 1 conexion WebSocket exitosa (Escenario 1).
- [ ] **Smoke test**: 1 evento broadcast recibido correctamente.
- [ ] **Formato payload**: Todos los eventos siguen `{t, d}` segun IMPORTANT_PRACTICES.md Regla 4.
- [ ] **Tipos de evento**: MESSAGE_CREATE, NOTIFICATION_CREATE, FRIENDSHIP_STATUS_CHANGED llegan correctamente.
- [ ] **Canal privado**: Usuario no autorizado NO recibe eventos del canal.
- [ ] **Canal privado**: Usuario autorizado SI recibe eventos del canal.
- [ ] **client_uuid**: El payload incluye `client_uuid` para deduplicacion.
- [ ] **Fechas ISO 8601**: Todos los timestamps en formato ISO 8601.
- [ ] **UUIDs como string**: Todos los UUIDs se castean a `(string)`.

### Pruebas de Carga

- [ ] **Carga media**: 100 VUs sin errores (Escenario 2).
- [ ] **Carga alta**: 1,000 VUs con latencia P99 < 500ms (Escenario 3).
- [ ] **Estres**: Punto de quiebre documentado (Escenario 4).
- [ ] **Pico**: 10,000 VUs sin colapso del sistema (Escenario 5).
- [ ] **Cola broadcasts**: Jobs no se acumulan mas alla de 1000 pendientes.
- [ ] **Redis**: Memoria no supera el 80% del limite configurado.

### Pruebas de Resiliencia

- [ ] **Reconexion**: Cliente se reconecta tras perder conexion y recibe eventos perdidos.
- [ ] **Integridad**: Mensajes no se pierden ni duplican tras reconexion.
- [ ] **Worker caido**: Si un worker de cola falla, otro retoma los jobs.
- [ ] **Redis caido**: El sistema maneja graceful degradation si Redis falla.
- [ ] **Reverb reinicio**: Reverb se reinicia sin perder mensajes encolados.

### Pruebas de Seguridad

- [ ] **Autenticacion**: Canales privados requieren token Sanctum valido.
- [ ] **Autorizacion**: Gate/Policies protegen el acceso a canales.
- [ ] **Rate limiting**: Endpoints POST tienen throttle (10 requests/minuto).
- [ ] **Inyeccion**: Payloads maliciosos no rompen el broadcast.
- [ ] **Limite de conexiones**: Reverb no acepta mas conexiones de las configuradas.

### Documentacion de Resultados

- [ ] Resultados de smoke test registrados.
- [ ] Resultados de carga media registrados.
- [ ] Resultados de carga alta registrados.
- [ ] Punto de quiebre documentado (con grafica de VUs vs latencia).
- [ ] Recomendaciones de capacidad para produccion documentadas.

---

## 8. Notas Importantes

### 8.1 Limitaciones de k6 con Reverb

k6 utiliza el modulo `k6/ws` que implementa el protocolo WebSocket estandar (RFC 6455). Sin embargo, Reverb utiliza el protocolo Pusher, que es una capa de aplicacion sobre WebSocket. Esto significa que:

- k6 puede conectarse a Reverb, suscribirse a canales y enviar/recibir mensajes.
- k6 **no puede** utilizar las caracteristicas avanzadas de Pusher (presence channels, client events) sin un adaptador.
- Para pruebas funcionales completas del protocolo Pusher, usa un script Node.js con `pusher-js`.

**Alternativa**: Usar Artillery con el plugin `artillery-engine-ws`, que tiene mejor soporte para protocolos personalizados sobre WebSocket.

### 8.2 Acerca del Entorno de Pruebas

> **Estas pruebas se ejecutan LOCALMENTE. En produccion, con servidores dedicados, balanceadores de carga y Redis en cluster, los resultados serian superiores.**

Factores que afectan los resultados locales:

- El hardware local (CPU, RAM, red) es compartido entre cliente y servidor.
- No hay balanceo de carga ni replicacion de Redis.
- El latency de red es 0 (todo corre en localhost).
- En produccion, la latencia de red anyadira 10-50ms adicionales.

### 8.3 Dependencia con Horizon y Pulse

> **Horizon y Pulse se documentan en `07_observability_horizon_pulse.md` y deben implementarse antes de las pruebas de carga para tener visibilidad del sistema bajo estres.**

Sin Horizon/Pulse, las pruebas de carga son ciegas: solo ves sintomas (latencia alta), no causas (jobs acumulados, queries lentas, Redis saturado).

### 8.4 Thresholds Recomendados para CI/CD

Si integras estas pruebas en un pipeline CI/CD, usa estos thresholds iniciales (ajustables segun el hardware):

```javascript
thresholds: {
  // Latencia
  'ws_connecting_duration': ['p(95) < 500'],
  'http_req_duration': ['p(99) < 1000'],

  // Conexiones exitosas
  'ws_sessions': ['rate > 0.95'],

  // Errores
  'http_req_failed': ['rate < 0.05'],
}
```

---

## 9. Referencias

- [k6 Documentation](https://k6.io/docs/)
- [k6 WebSocket module](https://k6.io/docs/javascript-api/k6-ws/)
- [Artillery Documentation](https://www.artillery.io/docs)
- [Laravel Reverb Docs](https://laravel.com/docs/13.x/reverb)
- [Laravel Horizon Docs](https://laravel.com/docs/13.x/horizon)
- [Laravel Pulse Docs](https://laravel.com/docs/13.x/pulse)
- [Redis INFO Command](https://redis.io/commands/info/)
- Documento previo: [`07_observability_horizon_pulse.md`](07_observability_horizon_pulse.md)
- Documento de testing funcional: [`05_testing_backend_without_frontend.md`](05_testing_backend_without_frontend.md)
- [Vyntra Mandatory Patterns](../../architecture/mandatory_patterns.md)
- [Vyntra Important Practices](../../architecture/IMPORTANT_PRACTICES.md)
