# 01 — Instalación de Redis y Reverb

> **Propósito**: Guía paso a paso para instalar y configurar la infraestructura de mensajería en tiempo real: Redis (cache/queues/pub-sub) y Laravel Reverb (WebSocket server).
> **Stack**: Laravel 13 + Redis + Reverb.
> **Audiencia**: DevOps y desarrolladores backend.

---

> [!IMPORTANT]
> **Este documento cubre instalación para DESARROLLO LOCAL (portfolio) y PRODUCCIÓN.**
>
> - Las secciones marcadas como **✅ DESARROLLO** o **✅ LOCAL** son para tu máquina local (portfolio).
> - Las secciones marcadas como **🖥️ PRODUCCIÓN SOLO** o **⚠️ PRODUCCIÓN** son solo para despliegue en servidor real.
> - Las secciones marcadas como **🔧 OPCIONAL** son valor agregado — puedes implementarlas o saltarlas.
>
> **Si estás construyendo un portfolio, sigue solo las secciones marcadas como ✅ DESARROLLO.**

---

## 0. Prerrequisitos y Orden de Implementación

> ✅ **EMPIEZA POR ESTE DOCUMENTO**. Es el primer paso obligatorio de la capa de eventos.

### 0.1 ¿Qué se instala aquí?

Este documento instala la **infraestructura base** que todos los demás documentos necesitan:
- **Redis**: Base de datos en memoria para colas, cache y pub/sub.
- **Reverb**: Servidor WebSocket que empuja eventos en tiempo real a los clientes.
- **Queue worker**: `php artisan queue:work --queue=broadcasts,default` procesa los eventos broadcast en segundo plano.

### 0.2 Estado del Frontend

> ⚠️ **El frontend NO está listo para WebSockets.**
>
> Verificado en `vyntra-frontend`:
> - `package.json` NO tiene `laravel-echo`, `pusher-js`, ni librería WebSocket.
> - NO hay referencias a WebSocket, Echo, Pusher, ni Reverb en ningún archivo `.js`.
> - Todos los servicios usan **mock data** (datos falsos), no llaman al backend real.
>
> **Conclusión:** Este documento instala **solo backend**. El frontend se conectará en una FASE FRONTEND posterior.

### 0.3 Prerrequisitos del Sistema

| # | Prerrequisito | Verificación |
|---|--------------|-------------|
| 1 | PHP 8.2+ con extensiones `sockets`, `pcntl` | `php -m \| grep -E 'sockets\|pcntl'` |
| 2 | Composer actualizado | `composer --version` |
| 3 | PostgreSQL corriendo | `php artisan migrate:status` |
| 4 | Laravel 13 instalado | `php artisan --version` |

### 0.4 Fases de Implementación

```
┌─────────────────────────────────────────────────────────┐
│  FASE 1: INFRAESTRUCTURA BACKEND (Este documento)        │
│  ├── Paso 1: Instalar Redis                             │
│  ├── Paso 2: Instalar Reverb                            │
│  ├── Paso 3: Crear routes/channels.php                  │
│  ├── Paso 4: Registrar BroadcastServiceProvider         │
│  ├── Paso 5: Configurar .env                            │
│  └── Paso 6: Configurar config/queue.php (cola broadcasts)│
├─────────────────────────────────────────────────────────┤
│  FASE 2: ARQUITECTURA BACKEND (02_events_architecture.md)│
│  ├── Crear app/Events/                                    │
│  ├── Definir payload {t, d}                             │
│  └── Documentar canales y eventos                         │
├─────────────────────────────────────────────────────────┤
│  FASE 3: CASOS DE USO BACKEND                           │
│  ├── 03_notifications_system.md                          │
│  └── 04_friendships_realtime.md                        │
├─────────────────────────────────────────────────────────┤
│  FASE 4: VERIFICACIÓN (testear sin frontend)             │
│  ├── wscat / Postman / script Node.js                    │
│  └── Verificar que eventos llegan por WebSocket         │
├─────────────────────────────────────────────────────────┤
│  FASE 5: OPTIMIZACIÓN BACKEND (06_octane_setup.md)       │
│  ├── Instalar Octane                                     │
│  └── Adaptar código para compatibilidad                  │
├─────────────────────────────────────────────────────────┤
│  FASE 6: FRONTEND (futuro)                              │
│  ├── Instalar laravel-echo + pusher-js                   │
│  ├── Crear provider global de WebSocket                  │
│  └── Modificar hooks para escuchar eventos               │
└─────────────────────────────────────────────────────────┘
```

### 0.5 Nota Importante

Este documento **no asume nada**. Es el punto de partida del **backend**. Si sigues este documento paso a paso, al final tendrás:
- Redis corriendo
- Reverb corriendo (`php artisan reverb:start`)
- Worker corriendo (`php artisan queue:work --queue=broadcasts,default`)
- `routes/channels.php` con autenticación básica
- `BroadcastServiceProvider` registrado
- `BROADCAST_CONNECTION=reverb` en `.env`
- Cola `broadcasts` configurada

**Solo después de terminar este documento**, pasa a `02_events_architecture.md`.

---

## 1. Problema que Resuelve

Vyntra actualmente no tiene WebSockets, colas dedicadas ni cache distribuido. Esto significa que:
- Los eventos broadcast se ejecutan síncronamente en el request HTTP (bloqueante y lento).
- No hay forma de escalar WebSockets horizontalmente (múltiples instancias Reverb).
- No hay monitorización de jobs en cola.
- No hay sistema de rate limiting distribuido.

**Objetivo**: Instalar la infraestructura mínima necesaria para soportar 300K usuarios concurrentes en tiempo real.

---

## 2. Redis

### 2.1 ¿Por qué Redis?

Redis se usa en Vyntra para tres propósitos:
1. **Queues**: El worker procesa jobs de broadcast (`broadcasts` queue) y jobs de negocio (`default` queue).
2. **Cache/Sessions**: Cache de autorización de canales, sesiones de usuario, rate limiting.
3. **Pub/Sub entre nodos Reverb**: Cuando `REVERB_SCALING_ENABLED=true`, Redis replica mensajes entre múltiples instancias Reverb.

### 2.2 Instalación

#### ✅ DESARROLLO — Opción A: Docker (recomendado para desarrollo)

```bash
# Descargar e iniciar Redis (1 solo comando)
docker run -d --name redis -p 6379:6379 redis:7

# Verificar
redis-cli ping
# Respuesta: PONG

# Para detener: docker stop redis
# Para eliminar: docker rm -f redis
```

#### ✅ DESARROLLO — Opción B: Redis local (desarrollo sin Docker)

```bash
# Windows (WSL2 recomendado)
sudo apt update
sudo apt install redis-server
sudo service redis-server start

# Verificar
redis-cli ping
# Respuesta: PONG
```

#### 🖥️ PRODUCCIÓN SOLO — Opción C: Redis Cloud / Managed

→ Ver [production/production_redis_cloud.md](production/production_redis_cloud.md) para detalles de configuración con Redis Cloud, AWS ElastiCache, DigitalOcean Managed Redis u Upstash.

### 2.3 Configuración en Laravel

```php
// config/database.php (ya existe por defecto en Laravel 13)
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'), // o 'predis'

    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_DB', 0),
    ],

    'cache' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_CACHE_DB', 1),
    ],

    'queues' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD', null),
        'port' => env('REDIS_PORT', 6379),
        'database' => env('REDIS_QUEUE_DB', 2),
    ],
],
```

### 2.4 Extensiones PHP

```bash
# Opción 1: phpredis (recomendado, más rápido — producción y desarrollo)
pecl install redis
# Agregar 'extension=redis.so' a php.ini

# ✅ DESARROLLO — Opción 2: predis (solo PHP, sin extensiones nativas)
composer require predis/predis
# ⚠️ predis es más fácil de instalar (solo Composer) pero ~2x más lento que phpredis.
#   En producción se recomienda migrar a phpredis cuando sea posible.
```

### 2.5 Migración de Cache a Redis

```php
// .env
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

### 🖥️ PRODUCCIÓN SOLO — 2.6 Consideraciones de Escalabilidad

→ Ver [production/production_horizontal_scaling.md](production/production_horizontal_scaling.md) para escalado horizontal de Redis (Cluster, Sentinel, separación de responsabilidades, persistencia AOF).

---

## 3. Laravel Reverb

### 3.1 Instalación

```bash
# Reverb se instala con el comando de broadcasting
php artisan install:broadcasting

# Esto instala:
# - laravel/reverb
# - config/reverb.php
# - Actualiza routes/channels.php
# - Actualiza .env con variables REVERB_*
```

### 3.2 Variables de Entorno

```env
# .env
REVERB_APP_ID=vyntra-app
REVERB_APP_KEY=vyntra-key
REVERB_APP_SECRET=vyntra-secret

REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# 🖥️ PRODUCCIÓN SOLO — Configuración para servidor real
# → Ver production/production_nginx_reverse_proxy.md para SSL y Nginx
# → Ver production/production_horizontal_scaling.md para escalado horizontal
# REVERB_SERVER_HOST=0.0.0.0
# REVERB_SERVER_PORT=8080
# REVERB_HOST=ws.vyntra.com
# REVERB_PORT=443
# REVERB_SCHEME=https
# REVERB_SCALING_ENABLED=true
```

### 3.3 Configuración

```php
// config/reverb.php
return [
    'default' => 'reverb',

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => 10_000,
            // 🖥️ PRODUCCIÓN SOLO — → Ver production/production_horizontal_scaling.md
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
            'pulse' => [
                'interval' => 15,
            ],
        ],
    ],

    'apps' => [
        [
            'id' => env('REVERB_APP_ID'),
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'capacity' => null,
            'allowed_origins' => ['*'], // 🖥️ PRODUCCIÓN SOLO: → Ver production/production_nginx_reverse_proxy.md
        ],
    ],
];
```

### 3.4 Iniciar el Servidor

```bash
# ✅ DESARROLLO
php artisan reverb:start

# 🖥️ PRODUCCIÓN SOLO → Ver production/production_server_tuning.md para Supervisor

# 🔧 Debug (útil en desarrollo y para diagnosticar problemas en producción)
php artisan reverb:start --debug

# Reinicio graceful (termina conexiones activas antes de cerrar)
php artisan reverb:restart
```

### 🖥️ PRODUCCIÓN SOLO — 3.5 Nginx Reverse Proxy

→ Ver [production/production_nginx_reverse_proxy.md](production/production_nginx_reverse_proxy.md) para configuración completa de Nginx reverse proxy con WebSocket headers.

### 🖥️ PRODUCCIÓN SOLO — 3.6 SSL

→ Ver [production/production_nginx_reverse_proxy.md](production/production_nginx_reverse_proxy.md) para configuración SSL con Let's Encrypt.

### 🖥️ PRODUCCIÓN SOLO — 3.7 Consideraciones de Producción

→ Ver [production/production_server_tuning.md](production/production_server_tuning.md) para ajustes de servidor: Open Files Limit, ext-uv, Nginx Worker Connections y Supervisor para Reverb.

### 🖥️ PRODUCCIÓN SOLO — 3.8 Escalado Horizontal

→ Ver [production/production_horizontal_scaling.md](production/production_horizontal_scaling.md) para escalado horizontal de Reverb con múltiples nodos, Load Balancer y Redis Pub/Sub.

### 🔧 OPCIONAL (valor para portfolio) — 3.9 Monitoreo con Pulse

→ Ver [production/optional_pulse.md](production/optional_pulse.md) para instalación y configuración de Laravel Pulse con dashboards de Reverb.

---

## 4. Cola Dedicada para Broadcasts

```php
// En cada evento broadcast
public function broadcastQueue(): string
{
    return 'broadcasts';
}
```

### 4.1 Ejecutar el Worker

```bash
# ✅ DESARROLLO
php artisan queue:work --queue=broadcasts,default

# 🖥️ PRODUCCIÓN SOLO — Worker gestionado por Supervisor
→ Ver [production/production_server_tuning.md](production/production_server_tuning.md) para configuración de Supervisor.

---

## 5. Checklist de Instalación

### Redis

| # | Tarea | Entorno |
|---|-------|---------|
| 1 | `Instalar Redis` (Docker, local o managed) | ✅ LOCAL / 🖥️ PROD |
| 2 | `Instalar extensión` `phpredis` (`pecl install redis`) | ✅ LOCAL / 🖥️ PROD |
| 3 | `Instalar predis/predis` (alternativa solo PHP) | ✅ LOCAL |
| 4 | `Configurar .env`: `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | ✅ LOCAL / 🖥️ PROD |
| 5 | `Configurar .env`: `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis` | ✅ LOCAL / 🖥️ PROD |
| 6 | `Verificar conectividad`: `redis-cli ping` | ✅ LOCAL / 🖥️ PROD |

### Reverb

| # | Tarea | Entorno |
|---|-------|---------|
| 1 | `Ejecutar` `php artisan install:broadcasting` | ✅ LOCAL / 🖥️ PROD |
| 2 | `Configurar` `config/reverb.php` con `apps`, `allowed_origins` | ✅ LOCAL / 🖥️ PROD |
| 3 | `Configurar` `.env` con `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | ✅ LOCAL / 🖥️ PROD |
| 4 | `Configurar` `.env` con `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | ✅ LOCAL / 🖥️ PROD |
| 5 | `Verificar que routes/channels.php` tiene autenticación de canales | ✅ LOCAL / 🖥️ PROD |

### Worker de Colas

| # | Tarea | Entorno |
|---|-------|---------|
| 1 | `Verificar que los eventos implementan` `broadcastQueue(): 'broadcasts'` | ✅ LOCAL / 🖥️ PROD |

### Verificación Post-Instalación

| # | Tarea | Entorno |
|---|-------|---------|
| 1 | `php artisan reverb:start` inicia sin errores | ✅ LOCAL / 🖥️ PROD |
| 2 | `redis-cli ping` responde `PONG` | ✅ LOCAL / 🖥️ PROD |
| 3 | `php artisan queue:work redis --queue=broadcasts` procesa jobs | ✅ LOCAL / 🖥️ PROD |
| 4 | Test: evento broadcast llega al frontend vía WebSocket | ✅ LOCAL / 🖥️ PROD |

---

## 7. Referencias de Producción

> 🖥️ Los siguientes documentos contienen las secciones de producción extraídas de esta guía.
> Solo son necesarios si estás desplegando en un servidor real.

| Documento | Contenido |
|-----------|-----------|
| [production/production_nginx_reverse_proxy.md](production/production_nginx_reverse_proxy.md) | Nginx Reverse Proxy + SSL (secciones 3.5 y 3.6) |
| [production/production_server_tuning.md](production/production_server_tuning.md) | Ajustes del servidor: ulimit, ext-uv, Nginx workers, Supervisor (sección 3.7) |
| [production/production_horizontal_scaling.md](production/production_horizontal_scaling.md) | Escalado horizontal: Redis Cluster/Sentinel, Reverb multi-nodo (secciones 2.6 y 3.8) |
| [production/production_redis_cloud.md](production/production_redis_cloud.md) | Redis Cloud / Managed (sección 2.2 Opción C) |
| [production/optional_pulse.md](production/optional_pulse.md) | Monitoreo con Pulse — opcional (sección 3.9) |

---

## 6. Referencias

- [Laravel Reverb Docs](https://laravel.com/docs/13.x/reverb)
- [Laravel Redis Docs](https://laravel.com/docs/13.x/redis)
- [Vyntra Mandatory Patterns](../../architecture/mandatory_patterns.md)
- [Vyntra Important Practices](../../architecture/IMPORTANT_PRACTICES.md)
