# Capas de Tiempo Real — Vyntra

> Guías para toda la infraestructura de tiempo real y escalabilidad del proyecto Vyntra.
>
> **Estado**: Implementación en progreso.
> **Stack**: Laravel 13 + Reverb + Redis + Octane + PgBouncer + PostgreSQL (primary + read replica).

---

## 📋 Estructura de esta carpeta

Esta carpeta agrupa 2 sub-capas que se implementan en orden:

| # | Sub-carpeta | Qué cubre | Estado |
|:-:|---|---|------|
| 1 | [`events_layer/`](events_layer/README.md) | Eventos broadcast, canales WebSocket, Redis queue, Reverb | ✅ 20/20 eventos implementados |
| 2 | [`bd_scaling/`](bd_scaling/README.md) | PgBouncer, read replica, escalado horizontal del monolito | 📋 Pendiente |

---

## 🗺️ Hoja de Ruta (orden de ejecución)

```
FASE 1: INFRASTRUCTURE SETUP
└── events_layer/01_redis_reverb_setup.md  ← ✅ Hecho
    ├── Redis 7 (Docker)
    ├── Laravel Reverb
    └── Cola broadcasts en config/queue.php

FASE 2: EVENTOS BROADCAST
└── events_layer/02_events_architecture.md  ← ✅ Hecho
    ├── 20 eventos en app/Events/
    ├── 5 patrones (A/B/C/D/E)
    └── routes/channels.php

FASE 3: CASOS DE USO
├── events_layer/03_notifications_system.md  ← Pendiente
└── events_layer/04_friendships_realtime.md  ← Pendiente

FASE 4: VERIFICACIÓN
└── events_layer/05_testing_backend_without_frontend.md  ← Pendiente

FASE 5: OPTIMIZACIÓN
└── events_layer/06_octane_setup.md  ← Pendiente

FASE 6: ESCALABILIDAD DE BD  ← Nueva fase
└── bd_scaling/
    ├── 00_concepts.md
    ├── 01_pgbouncer_setup.md
    ├── 02_read_replica_setup.md
    ├── 03_database_config_laravel.md
    ├── 04_horizontal_scaling.md
    └── 05_scaling_strategy.md
```

---

## 🎯 Objetivo de Escalabilidad

Vyntra aspira a aguantar:
- **30K conexiones WebSocket simultáneas** (Reverb)
- **60K HTTP requests en burst** (mix reads + writes)
- **Sin microservicios** (monolito escalable horizontalmente)

**Stack para alcanzarlo:**
- PostgreSQL primary + read replica (separar reads/writes)
- PgBouncer (connection pooling, 25 conexiones reales para 1000 clientes)
- Laravel Octane (Swoole, PHP en RAM)
- Redis (solo broadcast queue, NO write buffer)
- Reverb (WebSocket server, escala horizontal con Redis Pub/Sub)

---

## 📚 Convenciones Transversales

- **YAGNI** (`mandatory_patterns.md` §18): no implementar más de lo necesario
- **No broadcast síncrono** en controllers (`ShouldBroadcast + ShouldQueue`)
- **Payload `{t, d}`** estilo Discord Gateway
- **client_uuid** para idempotencia y deduplicación
- **0 cambios en código de app** para escalabilidad (todo es config + docker)

---

## 📖 Referencias

- [mandatory_patterns.md](../../architecture/mandatory_patterns.md) — Patrones obligatorios
- [real_time_rules.md](../../architecture/real_time_rules.md) — Reglas de tiempo real
- [realtime_data_lifecycle.md](../../architecture/realtime_architecture/realtime_data_lifecycle.md) — Flujo de datos en tiempo real
- [http_request_lifecycle.md](../../architecture/realtime_architecture/http_request_lifecycle.md) — Flujo HTTP
