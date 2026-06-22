# 📑 Contrato de Interfaz API — Proyecto Vyntra (Vyne)

Contrato de comunicación entre el Frontend (Next.js / React Query) y el Backend (Laravel 11). Fuente de verdad única para la sincronización de payloads, validadores y respuestas.

---

## 1. Reglas Globales

| Regla | Especificación |
| :--- | :--- |
| **Formato** | `application/json` en todos los cuerpos de petición y respuesta. |
| **Naming Convention** | `snake_case` en todos los atributos del payload JSON. |
| **Identificadores** | UUID v4 (string). Nunca IDs numéricos auto-incrementales expuestos. |
| **Fechas** | ISO 8601 — `YYYY-MM-DDTHH:mm:ss.sssZ`. |
| **Idempotencia (POST)** | Las peticiones de creación física de recursos requieren un `client_uuid` (UUID v4 generado en el cliente) para deduplicación en tiempo real. |
| **Idempotencia (PATCH / Pivot)** | Las peticiones de actualización parcial (`PATCH`) y las operaciones sobre tablas pivot son idempotentes por naturaleza — `client_uuid` no requerido (`sometimes` como máximo). |
| **Autenticación** | Bearer Token en el header `Authorization` — gestionado con Laravel Sanctum. |

### Árbol de Decisión — `client_uuid`

Lógica de inclusión de `client_uuid` en `FormRequest` del backend y payloads del frontend.

![Árbol de Decisión de Idempotencia](./architecture/assets/vyntra_idempotency_tree.png)

```mermaid
flowchart TD
    START["🔵 Nueva Petición HTTP\nal Backend de Vyntra"] --> Q1{"¿Método HTTP?"}

    Q1 -->|"POST (Crear)"| Q2{"¿Crea un registro físico\nen tabla principal de la BD?"}
    Q1 -->|"PATCH (Actualizar)"| R3["❌ client_uuid = NO REQUERIDO"]

    Q2 -->|"✅ SÍ — INSERT en tabla principal"| R1["✅ client_uuid = REQUIRED"]
    Q2 -->|"❌ NO — Relación pivot / Many-to-Many"| R2["⚡ client_uuid = SOMETIMES"]

    R1 --- E1["StoreClubRequest\nStoreChannelMessageRequest\nStoreDmMessageRequest\nStoreClubCategoryRequest\nStoreClubChannelRequest\nStoreClubRoleRequest"]

    R2 --- E2["AssignClubRoleRequest\n(syncWithoutDetaching — idempotente)"]

    R3 --- E3["UpdateClubRequest · UpdateUserRequest\nUpdateClubChannelRequest · UpdateClubCategoryRequest\nUpdateClubRoleRequest · RespondFriendshipRequest"]

    style START fill:#1e3a5f,stroke:#4da6ff,color:#fff
    style Q1 fill:#3d1f56,stroke:#a855f7,color:#fff
    style Q2 fill:#0f4c4c,stroke:#2dd4bf,color:#fff
    style R1 fill:#1a4731,stroke:#22c55e,color:#fff
    style R2 fill:#4a3520,stroke:#f97316,color:#fff
    style R3 fill:#5c1a1a,stroke:#ef4444,color:#fff
    style E1 fill:#111827,stroke:#6b7280,color:#9ca3af
    style E2 fill:#111827,stroke:#6b7280,color:#9ca3af
    style E3 fill:#111827,stroke:#6b7280,color:#9ca3af
```

---

## 2. Esquema de Entidades

### Usuario (`User`)

```json
{
  "uuid": "string",
  "username": "string",
  "user_tag": "string",
  "first_name": "string",
  "last_name": "string",
  "email": "string",
  "avatar_url": "string|null",
  "banner_url": "string|null",
  "bio": "string|null",
  "location": "string|null",
  "is_online": "boolean",
  "created_at": "string",
  "updated_at": "string"
}
```

### Mensaje de Chat (`ChatMessage`)

Estructura compartida para mensajes de canal de club y mensajes directos (DM).

```json
{
  "uuid": "string",
  "client_uuid": "string",
  "content": "string",
  "status": "sent|sending|error",
  "parent_message_uuid": "string|null",
  "sender_uuid": "string",
  "channel_uuid": "string|null",
  "dm_conversation_uuid": "string|null",
  "created_at": "string",
  "updated_at": "string",
  "user": {
    "uuid": "string",
    "username": "string",
    "avatar_url": "string|null"
  }
}
```

### Club y Estructura Organizativa

- **Club:** `{ uuid, name, description|null, avatar_url|null, banner_url|null, owner_uuid, category_tag, created_at, updated_at }`
- **Category:** `{ uuid, club_uuid, name, sort_order, is_private, created_at, updated_at }`
- **Channel:** `{ uuid, category_uuid, name, description|null, type: "text|voice", sort_order, is_private, created_at, updated_at }`
- **ClubRole:** `{ uuid, club_uuid, name, color: "#HEXHEX", permissions: integer (bitmask — ver `app/Enums/ClubPermission.php`), created_at, updated_at }`

#### Permisos bitwise

El campo `permissions` de `ClubRole` es un **integer (bitmask)**. Cada permiso es un bit:

| Bit | Constante PHP | Constante Frontend | Descripción |
|-----|---------------|---------------------|-------------|
| `1 << 0`  | `ClubPermission::VIEW_CHANNELS`    | `PERMISSIONS.VIEW_CHANNELS`    | Ver canales (incluye privados) |
| `1 << 1`  | `ClubPermission::MANAGE_CHANNELS`  | `PERMISSIONS.MANAGE_CHANNELS`  | Crear/editar/eliminar canales y categorías |
| `1 << 2`  | `ClubPermission::MANAGE_ROLES`     | `PERMISSIONS.MANAGE_ROLES`     | Crear/editar/eliminar roles |
| `1 << 3`  | `ClubPermission::MANAGE_CLUB`      | `PERMISSIONS.MANAGE_CLUB`      | Editar ajustes del club |
| `1 << 4`  | `ClubPermission::CREATE_INVITE`    | `PERMISSIONS.CREATE_INVITE`    | Crear invitaciones |
| `1 << 5`  | `ClubPermission::CHANGE_NICKNAME`  | `PERMISSIONS.CHANGE_NICKNAME`  | Cambiar nickname propio |
| `1 << 6`  | `ClubPermission::MANAGE_NICKNAMES` | `PERMISSIONS.MANAGE_NICKNAMES` | Cambiar nickname de otros |
| `1 << 7`  | `ClubPermission::KICK_MEMBERS`     | `PERMISSIONS.KICK_MEMBERS`     | Expulsar miembros |
| `1 << 8`  | `ClubPermission::BAN_MEMBERS`      | `PERMISSIONS.BAN_MEMBERS`      | Banear miembros |
| `1 << 9`  | `ClubPermission::SEND_MESSAGES`    | `PERMISSIONS.SEND_MESSAGES`    | Enviar mensajes en canales |
| `1 << 10` | `ClubPermission::EMBED_LINKS`      | `PERMISSIONS.EMBED_LINKS`      | Insertar links en mensajes |
| `1 << 11` | `ClubPermission::ATTACH_FILES`     | `PERMISSIONS.ATTACH_FILES`     | Adjuntar archivos |
| `1 << 12` | `ClubPermission::ADD_REACTIONS`    | `PERMISSIONS.ADD_REACTIONS`    | Añadir reacciones |
| `1 << 13` | `ClubPermission::MANAGE_MESSAGES`  | `PERMISSIONS.MANAGE_MESSAGES`  | Borrar/editar mensajes ajenos |
| `1 << 14` | `ClubPermission::MENTION_EVERYONE` | `PERMISSIONS.MENTION_EVERYONE` | Mencionar @everyone |
| `1 << 30` | `ClubPermission::ADMINISTRATOR`    | `PERMISSIONS.ADMINISTRATOR`    | Bypass total (todos los permisos) |

Los bits están definidos en `app/Enums/ClubPermission.php` (backend) y `src/shared/constants/permissions.js` (frontend). Cualquier adición requiere actualizar ambos archivos.

**Operaciones bitwise:**
- `OR (|)` para acumular permisos de varios roles en un miembro.
- `AND (&)` para verificar si un permiso está presente.
- `XOR (^)` para toggle (activar/desactivar) un permiso individual.

---

## 3. Standard Envelopes

### Respuesta Exitosa — Objeto Único

```json
{
  "status": "success",
  "data": { ... }
}
```

### Respuesta Exitosa — Paginación por Cursor

```json
{
  "data": [ ... ],
  "meta": {
    "next_cursor": "string|null",
    "per_page": "number"
  }
}
```

### Error de Validación — HTTP 422

```json
{
  "status": "error",
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Mensaje descriptivo del error."]
  }
}
```

### Flujo de Envelopes por Resultado

```mermaid
flowchart TD
    REQ["📨 Petición"] --> CTRL["⚙️ Controller"]

    CTRL --> S200{"¿Resultado exitoso?"}

    S200 -->|"SÍ — Objeto único"| ENV1["✅ HTTP 200/201\n{ status: success, data: { ... } }"]
    S200 -->|"SÍ — Listado paginado"| ENV2["✅ HTTP 200\n{ data: [...], meta: { next_cursor, per_page } }"]
    S200 -->|"NO — Validación fallida"| ENV3["❌ HTTP 422\n{ status: error, message, errors: { field: [msg] } }"]

    style REQ fill:#1e3a5f,stroke:#4da6ff,color:#fff
    style CTRL fill:#3d1f56,stroke:#a855f7,color:#fff
    style S200 fill:#0f4c4c,stroke:#2dd4bf,color:#fff
    style ENV1 fill:#1a4731,stroke:#22c55e,color:#fff
    style ENV2 fill:#1a4731,stroke:#22c55e,color:#fff
    style ENV3 fill:#5c1a1a,stroke:#ef4444,color:#fff
```

---

## 4. Mapeo de Endpoints

Correspondencia técnica entre servicios del frontend, rutas, validadores (`FormRequest`) y controladores del backend.

| Módulo | Servicio Frontend | Método | Ruta | FormRequest | Controller@Método | Broadcast |
| :--- | :--- | :---: | :--- | :--- | :--- | :--- |
| **Auth** | `AuthService.login` | `POST` | `/api/auth/login` | `LoginRequest` | `AuthController@login` | — |
| | `AuthService.register` | `POST` | `/api/auth/register` | `StoreUserRequest` | `AuthController@register` | — |
| **Chat** | `ChatService.sendMessage` | `POST` | `/api/channels/{c_uuid}/messages` | `StoreChannelMessageRequest` | `ChannelMessageController@store` | `MessageCreated` vía Reverb |
| | `ChatService.sendDmMessage` | `POST` | `/api/dm-conversations/{dm_uuid}/messages` | `StoreDmMessageRequest` | `DmMessageController@store` | `DmMessageCreated` vía Reverb |
| **Club** | `ClubService.createClub` | `POST` | `/api/clubs` | `StoreClubRequest` | `ClubController@store` | `ClubCreated` |
| | `ClubService.updateClub` | `PATCH` | `/api/clubs/{club_uuid}` | `UpdateClubRequest` | `ClubController@update` | `ClubUpdated` |
| | `ClubService.createCategory` | `POST` | `/api/clubs/{club_uuid}/categories` | `StoreClubCategoryRequest` | `ClubCategoryController@store` | `CategoryCreated` |
| | `ClubService.updateCategory` | `PATCH` | `/api/clubs/{club_uuid}/categories` | `UpdateClubCategoryRequest` | `ClubCategoryController@update` | `CategoryUpdated` |
| | `ClubService.createChannel` | `POST` | `/api/clubs/{club_uuid}/channels` | `StoreClubChannelRequest` | `ClubChannelController@store` | `ChannelCreated` |
| | `ClubService.updateChannel` | `PATCH` | `/api/clubs/{club_uuid}/channels/{channel_uuid}` | `UpdateClubChannelRequest` | `ClubChannelController@update` | `ChannelUpdated` |
| | `ClubService.createRole` | `POST` | `/api/clubs/{club_uuid}/roles` | `StoreClubRoleRequest` | `ClubRoleController@store` | `RoleCreated` |
| | `ClubService.updateRole` | `PATCH` | `/api/clubs/{club_uuid}/roles` | `UpdateClubRoleRequest` | `ClubRoleController@update` | `RoleUpdated` |
| | `ClubService.assignRole` | `POST` | `/api/clubs/{club_uuid}/members/{user_uuid}/roles` | `AssignClubRoleRequest` | `ClubMemberRoleController@store` | `MemberRoleAssigned` |

> **Nota de convención (actualizado 2026-06-04):** Los identificadores de sub-recurso en operaciones PATCH (update de categorías, canales, roles) viajan en el **body** de la petición (`category_uuid`, `channel_uuid`, `uuid` respectivamente), no en la URL. Esto simplifica la firma del endpoint y mantiene el path estable. La convención para operaciones destructivas (DELETE) sí mantiene el identificador en URL.
| **User** | `UserService.updateUser` | `PATCH` | `/api/user` | `UpdateUserRequest` | `UserController@updateProfile` | — |
| | `NotificationService.respondToFriendRequest` | `PATCH` | `/api/user/friend-requests/{request_uuid}` | `RespondFriendshipRequest` | `FriendshipController@respond` | `FriendshipStatusChanged` |

### Mapa de Endpoints por Módulo

```mermaid
flowchart TB
    subgraph AUTH["🔐 AUTH"]
        direction LR
        A1["POST /api/auth/login\nLoginRequest → AuthController@login"]
        A2["POST /api/auth/register\nStoreUserRequest → AuthController@register"]
    end

    subgraph CHAT["💬 CHAT"]
        direction LR
        C1["POST /api/channels/{uuid}/messages\nStoreChannelMessageRequest → ChannelMessageController@store\n📡 MessageCreated"]
        C2["POST /api/dm-conversations/{uuid}/messages\nStoreDmMessageRequest → DmMessageController@store\n📡 DmMessageCreated"]
    end

    subgraph CLUB["🏛️ CLUB"]
        direction LR
        CL1["POST /api/clubs — StoreClubRequest"]
        CL2["PATCH /api/clubs/{uuid} — UpdateClubRequest"]
        CL3["POST .../categories — StoreClubCategoryRequest"]
        CL4["PATCH .../categories — UpdateClubCategoryRequest"]
        CL5["POST .../channels — StoreClubChannelRequest"]
        CL6["PATCH .../channels/{uuid} — UpdateClubChannelRequest"]
        CL7["POST .../roles — StoreClubRoleRequest"]
        CL8["PATCH .../roles — UpdateClubRoleRequest"]
        CL9["POST .../members/{uuid}/roles — AssignClubRoleRequest"]
    end

    subgraph USER["👤 USER"]
        direction LR
        U1["PATCH /api/user — UpdateUserRequest → UserController@updateProfile"]
        U2["PATCH /api/user/friend-requests/{uuid}\nRespondFriendshipRequest → FriendshipController@respond"]
    end

    FE["🌐 Next.js\n(Services + React Query)"] --> AUTH
    FE --> CHAT
    FE --> CLUB
    FE --> USER

    style FE fill:#1e3a5f,stroke:#4da6ff,color:#fff
    style AUTH fill:#1a2744,stroke:#60a5fa,color:#fff
    style CHAT fill:#1a3a2a,stroke:#34d399,color:#fff
    style CLUB fill:#3a2a1a,stroke:#f59e0b,color:#fff
    style USER fill:#2a1a3a,stroke:#c084fc,color:#fff
```
