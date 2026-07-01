# Capa de Eventos en Tiempo Real — Vyntra

> Guías de implementación para la infraestructura WebSocket, eventos broadcast y actualizaciones en tiempo real del proyecto Vyntra.
>
> **Estado**: Implementación en progreso.
> **Stack**: Laravel 13 + Reverb + Redis + Octane (frontend: Next.js + Echo + React Query).

---

## 📋 Orden de Ejecución (sigue los números)

> **Los números de los archivos = orden en que debes ejecutarlos.**
> No saltes pasos. Cada documento asume que completaste el anterior.

| # | Documento | Qué hace | Fase |
|:-:|---|---|------|
| 00 | [`00_concepts_and_guide_for_beginners.md`](00_concepts_and_guide_for_beginners.md) | **¡EMPIEZA AQUÍ!** Conceptos explicados como a un principiante | 📖 Teoría |
| 01 | [`01_redis_reverb_setup.md`](01_redis_reverb_setup.md) | **Paso 1 REAL**. Instalar Redis y Reverb, configurar todo | 🔧 Setup |
| 02 | [`02_events_architecture.md`](02_events_architecture.md) | Arquitectura de canales, payload {t, d}, autenticación | 🏗️ Diseño |
| 03 | [`03_notifications_system.md`](03_notifications_system.md) | Notificaciones broadcast | ⚙️ Implementar |
| 04 | [`04_friendships_realtime.md`](04_friendships_realtime.md) | Amistades en tiempo real | ⚙️ Implementar |
| 05 | [`05_testing_backend_without_frontend.md`](05_testing_backend_without_frontend.md) | **Probar sin frontend** (wscat, Postman, Node.js) | 🧪 Verificar |
| 06 | [`06_octane_setup.md`](06_octane_setup.md) | **ÚLTIMO**. Optimizar con Octane (solo si todo funciona) | 🚀 Optimizar |

---

## Explicación del Orden

Parece raro que el archivo `01` sea sobre Redis/Reverb y el `02` sea sobre arquitectura, pero tiene lógica:

1. **No puedes crear eventos broadcast si no tienes Redis + Reverb instalados**
2. **No puedes probar eventos si no los creaste**
3. **No puedes optimizar si no está funcionando**

Por eso el orden lógico es:
```
Instalar (01) → Diseñar (02) → Implementar (03, 04) → Probar (05) → Optimizar (06)
```

---

## Fases de Implementación

```
┌──────────────────────────────────────────────────────────┐
│  FASE 1: INSTALAR (01_redis_reverb_setup.md)             │
│  ├── Instalar Redis, Reverb                                │
│  ├── Crear routes/channels.php                           │
│  ├── Registrar BroadcastServiceProvider                  │
│  └── Configurar .env y cola broadcasts                   │
├──────────────────────────────────────────────────────────┤
│  FASE 2: DISEÑAR (02_events_architecture.md)             │
│  ├── Crear app/Events/                                   │
│  ├── Definir payload {t, d}                              │
│  └── Definir canales y eventos                           │
├──────────────────────────────────────────────────────────┤
│  FASE 3: IMPLEMENTAR (03_notifications + 04_friendships) │
│  ├── NotificationCreated + NotificationRead              │
│  └── FriendshipStatusChanged                             │
├──────────────────────────────────────────────────────────┤
│  FASE 4: VERIFICAR (05_testing_backend...)               │
│  ├── Testear con wscat / Postman / script Node.js        │
│  └── Verificar que eventos llegan correctamente          │
├──────────────────────────────────────────────────────────┤
│  FASE 5: OPTIMIZAR (06_octane_setup.md)                  │
│  └── Instalar Octane (solo si todo funciona)             │
├──────────────────────────────────────────────────────────┤
│  FASE 6: FRONTEND (futuro)                               │
│  ├── Instalar laravel-echo + pusher-js                   │
│  └── Conectar hooks de React Query a WebSocket           │
└──────────────────────────────────────────────────────────┘
```

---

## Convenciones Transversales

- **Formato de payload WebSocket**: `{t: 'EVENT_NAME', d: {...}}` según la [Regla 4 de `IMPORTANT_PRACTICES.md`](../architecture/IMPORTANT_PRACTICES.md).
- **No broadcast síncrono en controllers**: `dispatch(new XxxEvent(...))` que implementa `ShouldBroadcast + ShouldQueue`.
- **Deduplicación**: `client_uuid` en todos los payloads de creación; el frontend usa `client_uuid` para evitar duplicados en React Query.
- **Listeners modifican cache**: Los listeners de Echo nunca tocan la UI directamente; modifican el caché de React Query.
- **Casting explícito**: `(string)` en todos los UUIDs expuestos vía WebSocket.
- **Fechas**: ISO 8601 (`YYYY-MM-DDTHH:mm:ss.sssZ`) en todos los timestamps.

---

## Progreso de Implementación

- [ ] 00_concepts_and_guide_for_beginners.md — Leído
- [ ] 01_redis_reverb_setup.md — Instalado
- [ ] 02_events_architecture.md — Implementado
- [ ] 03_notifications_system.md — Implementado
- [ ] 04_friendships_realtime.md — Implementado
- [ ] 05_testing_backend_without_frontend.md — Verificado
- [ ] 06_octane_setup.md — Optimizado

> **Nota**: Marca las casillas a medida que avances.
