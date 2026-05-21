# 🏛️ Vyne Architecture: Manual de Ingeniería Frontend

Este documento detalla la arquitectura de alta escalabilidad implementada en **Vyne**, diseñada para soportar una carga de usuarios masiva (tipo Discord) manteniendo una deuda técnica mínima y una modularidad absoluta.

## Estructura de Directorios (Visual Map)

src/
├── app/ # Capa de Orquestación (Routing)
│ ├── (auth)/ # Grupo de rutas de autenticación
│ ├── clubs/ # Rutas de servidores y canales
│ ├── me/ # Rutas de Mensajes Directos y Amigos
│ └── layout.js # Root Layout (Providers globales)
│
├── features/ # Dominios de Negocio (Lógica)
│ ├── clubs/ # Lógica específica de servidores
│ ├── chat/ # Motor de mensajería y DMs
│ └── users/ # Gestión de perfiles y sesión
│ ├── components/ # UI específica del dominio (Atomic)
│ ├── hooks/ # Queries y Mutaciones (React Query)
│ └── stores/ # Estado local (Zustand)
│
├── shared/ # Componentes y Utilidades Agnosticas
│ ├── components/ui/ # UI Kit (Atoms, Molecules, Organisms)
│ ├── hooks/ # Hooks transversales (mobile, window)
│ ├── stores/ # Estado de UI global (useReplyStore)
│ └── utils/ # Helpers (formateo, UUIDs)
└── ...

## 1. El Tridente del Frontend (Layers)

La aplicación se divide en tres capas estrictamente aisladas para evitar el "espagueti" de código:

### 📂 `src/app` (Orquestación y Rutas)

- **Función:** Único punto de entrada. Define la jerarquía de URLs.
- **Regla de Oro:** No contiene lógica de negocio pesada. Solo "inyecta" datos y mutaciones de las `features` en los componentes de `shared`.
- **Estandarización:** Todas las rutas son en minúsculas (`/clubs`, `/me`) para máxima compatibilidad web.

### 📂 `src/features` (Dominios de Negocio)

- **Función:** Contiene la "inteligencia" de la app.
- **Sub-capas:**
  - `hooks/`: Lógica de fetching (React Query) y mutaciones con Optimistic Updates.
  - `stores/`: Estado global de la feature (Zustand).
  - `components/`: Organismos y moléculas que pertenecen _solo_ a este dominio (ej: `SidebarClub`).
- **Ejemplos:** `clubs`, `chat` (DMs), `users`, `notifications`.

### 📂 `src/shared` (El Motor Agnóstico)

- **Función:** Infraestructura reutilizable por toda la plataforma.
- **Modularidad:** Componentes puros que no conocen nada del backend o de otras features. Reciben todo vía `props` (Inversión de Control).
- **Ejemplos:** `ChatInput`, `ChatMessageList`, `UserAvatar`, `UI Kit`.

---

## 2. El Motor de Mensajería (Messaging Engine)

Hemos refactorizado el sistema de chat para que sea **100% agnóstico**.

- **UI Pura (`shared`):** El `ChatInput` y `ChatMessageList` son contenedores visuales que no saben si están en un Club o en un DM.
- **Inyección de Lógica:** La página (en `app`) o el componente de dominio (en `features`) inyecta la mutación correspondiente:
  - `useMutateChatMessages` (Canales de Club).
  - `useMutateDMChatMessages` (Mensajes Directos).
- **Resultado:** Reutilización total del código de UI con lógica de backend intercambiable.

---

## 3. Protocolo Vyne de Estado y Datos

Para garantizar una experiencia fluida y robusta, aplicamos tres reglas innegociables:

1.  **Patrón UUID Cliente (Deduplicación):** Todo mensaje nace con un ID generado en el frontend (`generateClientUUID`). Esto evita duplicados si el WebSocket y el HTTP llegan al mismo tiempo.
2.  **Anatomía de Mutación (Optimistic Updates):**
    - `onMutate`: Inyecta el dato en la caché instantáneamente con estado `sending`.
    - `onError`: Realiza un rollback automático al estado anterior si la red falla.
    - `onSettled`: Sincroniza la "verdad absoluta" con el servidor en segundo plano.
3.  **Idempotencia:** El backend (Laravel) debe respetar el `client_uuid` para no insertar el mismo registro dos veces.

---

## 4. Jerarquía Atomic Design

Organizamos los componentes visuales según su complejidad:

- **Atoms:** Elementos base (Botones, Inputs, Iconos).
- **Molecules:** Grupos pequeños con funcionalidad simple (Avatar + Nombre, Item de Navegación).
- **Organisms:** Componentes complejos que orquestan lógica (Header de Chat, Lista de Miembros).
- **Templates:** La estructura física de la página (Sidebar + Contenido).
- **Pages:** Los conectores que unen el Template con los datos reales.
