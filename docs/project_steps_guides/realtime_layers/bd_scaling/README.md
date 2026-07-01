# Escalabilidad de Base de Datos — Vyntra

> Guías de implementación para la escalabilidad de PostgreSQL, conexiones, replicación y escalado horizontal del monolito Vyntra.
>
> **Estado**: Pendiente de implementación.
> **Stack**: PostgreSQL 17 + PgBouncer + Redis 7 + Octane + Reverb.
> **Objetivo**: 30K conexiones WebSocket + 60K HTTP requests en burst (mix reads/writes).

---

## 📋 Orden de Ejecución (sigue los números)

> **Los números de los archivos = orden en que debes ejecutarlos.**
> No saltes pasos. Cada documento asume que completaste el anterior.
> Si vienes de la capa de eventos (`events_layer/`), esta capa es **paralela e independiente** — puedes empezar sin haber terminado la otra.

| # | Documento | Qué hace | Fase |
|:-:|---|---|------|
| 00 | [`00_concepts.md`](00_concepts.md) | **¡EMPIEZA AQUÍ!** Conceptos de escalabilidad de BD explicados para juniors | 📖 Teoría |
| 01 | [`01_pgbouncer_setup.md`](01_pgbouncer_setup.md) | **Paso 1 REAL**. Instalar PgBouncer entre Laravel y Postgres | 🔧 Setup |
| 02 | [`02_read_replica_setup.md`](02_read_replica_setup.md) | Configurar PostgreSQL primary + read replica (streaming replication) | 🗄️ BD |
| 03 | [`03_database_config_laravel.md`](03_database_config_laravel.md) | Configurar Laravel para enviar reads a replica, writes a primary | ⚙️ Config |
| 04 | [`04_horizontal_scaling.md`](04_horizontal_scaling.md) | Escalar el monolito horizontalmente (Octane + Reverb + replicas) | 🚀 Escalar |
| 05 | [`05_scaling_strategy.md`](05_scaling_strategy.md) | **RESUMEN**. Estrategia completa, tabla de decisiones, matemática final | 📊 Estrategia |

---

## Explicación del Orden

La escalabilidad de BD tiene una progresión natural:

1. **No puedes tener muchas conexiones** si no tienes un pooler (PgBouncer)
2. **No puedes tener replicas** si no configuraste replicación en Postgres
3. **No puedes usar replicas** si Laravel no sabe a cuál enviar cada query
4. **No puedes escalar el monolito** si la BD no aguanta más nodos
5. **No sabes si tienes suficiente** si no hiciste las cuentas

Por eso el orden lógico es:

```
Pooler (01) → Replicas (02) → Config Laravel (03) → Escalar horizontal (04) → Estrategia (05)
```

---

## Fases de Implementación

```
┌─────────────────────────────────────────────────────────────────────┐
│  FASE 1: TEORÍA (00_concepts.md)                                   │
│  ├── Entender qué es un pooler, una replica, escalado horizontal    │
│  ├── Aprender las analogías (restaurante, biblioteca, cajas)        │
│  └── Saber qué números esperar                                      │
├─────────────────────────────────────────────────────────────────────┤
│  FASE 2: POOLER (01_pgbouncer_setup.md)                             │
│  ├── Instalar PgBouncer en Docker                                   │
│  ├── Configurar pgbouncer.ini y userlist.txt                        │
│  ├── Conectar Laravel a PgBouncer                                   │
│  └── Verificar pools con SHOW POOLS                                 │
├─────────────────────────────────────────────────────────────────────┤
│  FASE 3: REPLICA (02_read_replica_setup.md)                         │
│  ├── Configurar primary para streaming replication                  │
│  ├── Crear replica con pg_basebackup                                │
│  ├── Configurar hot_standby en replica                              │
│  └── Medir replication lag                                          │
├─────────────────────────────────────────────────────────────────────┤
│  FASE 4: LARAVEL (03_database_config_laravel.md)                    │
│  ├── Modificar config/database.php con read/write hosts             │
│  ├── Agregar variables .env                                         │
│  ├── Verificar que Laravel usa la replica                           │
│  └── Entender sticky connections                                    │
├─────────────────────────────────────────────────────────────────────┤
│  FASE 5: HORIZONTAL (04_horizontal_scaling.md)                      │
│  ├── Escalar Octane con N procesos                                  │
│  ├── Escalar Reverb con Redis Pub/Sub                               │
│  ├── Agregar más replicas de lectura                                │
│  └── Diagrama de arquitectura multi-nodo                            │
├─────────────────────────────────────────────────────────────────────┤
│  FASE 6: ESTRATEGIA (05_scaling_strategy.md)                        │
│  ├── Tabla de decisiones tomadas y descartadas                      │
│  ├── Guía "Si crece X, hago Y"                                      │
│  ├── Matemática final: 30K WS + 60K HTTP                            │
│  └── Anti-patrones que NO cometimos                                 │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Convenciones Transversales

- **Pool mode**: PgBouncer usa `transaction` mode (no `session`) para máximo throughput.
- **Replicación**: Streaming replication asíncrona (no síncrona) para no afectar writes del primary.
- **Read/Write splitting**: Laravel lo maneja automáticamente via `config/database.php` — no necesitas un proxy.
- **Escalado horizontal**: Copias idénticas del monolito, NO microservicios.
- **Redis Pub/Sub**: Backbone de comunicación entre nodos Reverb.
- **Casting explícito**: `(string)` en todos los UUIDs que cruzan la red.

---

## Progreso de Implementación

- [ ] 00_concepts.md — Leído
- [ ] 01_pgbouncer_setup.md — Instalado
- [ ] 02_read_replica_setup.md — Configurado
- [ ] 03_database_config_laravel.md — Configurado
- [ ] 04_horizontal_scaling.md — Escalado
- [ ] 05_scaling_strategy.md — Resumen leído

> **Nota**: Marca las casillas a medida que avances.
