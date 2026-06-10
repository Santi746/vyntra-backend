---
name: laravel-scalability
description: "Implementa patrones de escalabilidad en Laravel: Octane (Swoole/RoadRunner), Reverb (WebSockets), Redis (cache/queues/sessions), y arquitecturas de alta concurrencia. Usa cuando pidas 'optimizar rendimiento', 'escalar Laravel', 'WebSockets', 'Octane', 'Redis', 'colas', 'caching avanzado'."
license: MIT
compatibility: opencode
metadata:
  audience: senior-developers
  stack: laravel-octane-reverb-redis
  workflow: scalability-audit-then-implement
---

# Laravel Scalability Expert

Skill para implementar y auditar patrones de escalabilidad en Laravel 13 con Octane, Reverb y Redis.

**IMPORTANTE**: Este proyecto usa Laravel 13.x (mayo 2026). NO asumir Laravel 11 ni 12. Las features de Laravel 13 incluyen:
- Octane integrado con mejoras de concurrencia
- Reverb con scaling nativo via Redis
- Redis 7.x con soporte para module loading
- Nuevos patrones de caching con tag invalidation mejorada
- Horizon v5+ con métricas en tiempo real

## Cuándo activarme

- "Optimiza el rendimiento de este endpoint"
- "Implementa WebSockets con Reverb"
- "Configura Octane con Swoole"
- "Necesito escalar las colas de Laravel"
- "Implementa caching con Redis"
- "Audita la escalabilidad de este módulo"

## Workflow de escalabilidad

### Fase 1: Auditoría (siempre primero)

Antes de implementar cualquier optimización:

1. **Identifica el bottleneck**:
   - ¿CPU-bound? → Octane + RoadRunner
   - ¿I/O-bound? → Redis caching + queue workers
   - ¿Real-time? → Reverb WebSockets
   - ¿Database? → Query optimization + read replicas

2. **Verifica el estado actual**:
   - Lee `config/octane.php` si existe
   - Lee `config/reverb.php` si existe
   - Lee `config/cache.php` y `config/queue.php` para driver actual
   - Revisa `composer.json` para paquetes de escalabilidad instalados

3. **Reporta hallazgos** antes de proponer cambios.

### Fase 2: Implementación por dominio

#### A. Laravel Octane (High-Performance Server)

**Cuando usar**: Aplicaciones con alto tráfico de lectura, APIs de alta concurrencia.

**Checklist de implementación**:
```
1. Instalar: composer require laravel/octane
2. Elegir servidor: swoole (mejor rendimiento) o roadrunner (más estable)
3. Configurar config/octane.php:
   - workers: CPU cores × 2
   - max_requests: 500-1000 (evita memory leaks)
   - cache: true (Octane cache)
   - concurrent: true (si usas Swoole)
4. Migrar código incompatible:
   - Singletons que mantienen estado mutable → refactorizar
   - Variables globales → request context
   - Static properties → dependency injection
5. Testing: php artisan octane:start --watch
```

**Patrones críticos**:
- **NO** usar `app()` o `resolve()` dentro de closures de Octane (memory leak)
- **SÍ** usar `Octane::concurrently()` para I/O paralelo
- **SÍ** usar `Octane::table()` para shared memory entre workers
- **NO** modificar config en runtime (Octane cachea config al iniciar)

#### B. Laravel Reverb (WebSockets)

**Cuando usar**: Chat en tiempo real, notificaciones push, live updates, presencia online.

**Checklist de implementación**:
```
1. Instalar: composer require laravel/reverb
2. Configurar config/reverb.php:
   - host: 0.0.0.0 (producción)
   - port: 8080
   - scaling: redis (para múltiples servidores)
3. Configurar broadcasting.php con driver 'reverb'
4. Implementar channels en routes/channels.php
5. Frontend: usar laravel-echo + reverb connector
```

**Patrones críticos**:
- **SIEMPRE** usar `broadcastOn()` con channel names dinámicos (`private-chat.{id}`)
- **SIEMPRE** autorizar channels privados con `Broadcast::channel()`
- **NUNCA** broadcast datos sensibles sin autorización
- **USAR** `ShouldBroadcastNow` para eventos críticos (sin cola)
- **USAR** `ShouldBroadcast` para eventos no críticos (con cola)
- **ESCALAR** con Redis para múltiples instancias de Reverb

#### C. Redis (Caching, Queues, Sessions)

**Cuando usar**: Cualquier aplicación que necesite rendimiento > 100 req/s.

**Checklist de implementación**:
```
1. Cache:
   - Configurar CACHE_DRIVER=redis en .env
   - Usar Cache::remember() para queries pesadas
   - Tagging para invalidación granular
   - TTLs específicos por tipo de dato

2. Queues:
   - Configurar QUEUE_CONNECTION=redis
   - Usar Horizon para monitoreo: composer require laravel/horizon
   - Separar colas por prioridad: default, high, low
   - Rate limiting con Redis: RateLimiter::for()

3. Sessions:
   - Configurar SESSION_DRIVER=redis
   - Solo si necesitas sesiones compartidas entre servidores
```

**Patrones críticos**:
- **SIEMPRE** usar `Cache::remember()` con TTL razonable (no infinito)
- **SIEMPRE** usar tags para cache de modelos: `Cache::tags(['users', 'user:1'])`
- **NUNCA** cachear datos que cambian frecuentemente sin estrategia de invalidación
- **USAR** `Cache::lock()` para operaciones críticas (evita race conditions)
- **USAR** Redis para rate limiting: `RateLimiter::tooManyAttempts()`
- **MONITOREAR** con Horizon: memoria, jobs fallidos, throughput

### Fase 3: Verificación post-implementación

Después de cualquier cambio de escalabilidad:

1. **Benchmark**: Comparar req/s antes y después
2. **Memory check**: Verificar que no haya leaks en Octane
3. **Queue health**: Verificar que los jobs se procesen correctamente
4. **WebSocket test**: Verificar conexiones y broadcasts
5. **Cache hit rate**: Verificar que el caching esté funcionando

## Reglas de oro de escalabilidad

1. **Medir antes de optimizar**: Sin métricas, no hay optimización.
2. **Cachear lo correcto**: No cachear todo, cachear lo que cuesta generar.
3. **Escalar horizontalmente**: Diseñar para múltiples instancias desde el inicio.
4. **Fail gracefully**: Si Redis cae, la app debe seguir funcionando (degradación elegante).
5. **Monitorizar siempre**: Sin monitoreo, no sabés cuándo escalar.

## Anti-patrones (NUNCA hacer)

-  Usar `DB::` queries sin índices en tablas grandes
- ❌ Cachear sin TTL o con TTL infinito
-  Usar `Session::` para datos que deberían estar en cache
- ❌ Broadcast sin autorización en channels privados
- ❌ Usar Octane sin testear memory leaks
-  Colas sincrónicas en producción
-  No usar Horizon para monitorear colas
- ❌ Hardcodear configuraciones de Redis/Octane

## Referencias técnicas

- Laravel Octane Docs: https://laravel.com/docs/octane
- Laravel Reverb Docs: https://laravel.com/docs/reverb
- Laravel Horizon Docs: https://laravel.com/docs/horizon
- Redis Best Practices: https://redis.io/docs/manual/
