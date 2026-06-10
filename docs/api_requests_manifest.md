# 📋 API Requests Manifest — Vyntra Backend (Laravel)

> **Propósito**: Documento AI-friendly que lista TODAS las peticiones HTTP que el Frontend (Next.js) realiza actualmente con mock data. Este manifiesto es la fuente de verdad para implementar rutas, controladores y `FormRequest` validators en Laravel.
>
> **Fecha de generación**: 2026-05-22
>
> **Estado del Backend**: Las rutas NO están implementadas. Solo existe `GET /api/user` (Sanctum default) y los FormRequest están mayormente vacíos.

---

## 🔑 Convenciones Globales (Obligatorias)

| Regla | Detalle |
|:---|:---|
| **Formato** | JSON (`application/json`) |
| **Naming** | `snake_case` para todos los atributos |
| **Identificadores** | Siempre `uuid` (string). Nunca IDs numéricos autoincrement expuestos |
| **Fechas** | ISO 8601 (`YYYY-MM-DDTHH:mm:ss.sssZ`) |
| **Deduplicación** | Toda mutación de creación envía `client_uuid` (uuid v4 generado en frontend). La columna `client_uuid` en BD debe ser `UNIQUE` |
| **Paginación** | Cursor-based obligatoria. Prohibido `offset`/`page` |
| **Auth** | Bearer token vía `Authorization` header (Laravel Sanctum) |
| **Envelope de respuesta** | `{ "status": "success", "data": {...} }` para objetos simples |
| **Envelope paginado** | `{ "data": [...], "meta": { "next_cursor": "string\|null", "per_page": number } }` |
| **Errores de validación** | `422` con `{ "status": "error", "message": "...", "errors": { "field": ["msg"] } }` |
| **Broadcasting** | Toda mutación de creación/edición que afecte a otros usuarios DEBE emitir `ShouldBroadcast` vía colas (Reverb). NUNCA broadcast síncrono |

---

## 🔒 AUTH — Autenticación

### AUTH-01: Registro de Usuario
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/auth/register` |
| **Frontend Service** | No existe servicio aún (feature `auth` solo tiene componentes) |
| **FormRequest existente** | `StoreUserRequest` ✅ (tiene reglas definidas) |
| **Estado** | ⚠️ NO IMPLEMENTADO EN CONTROLLER — El FormRequest ya tiene validaciones |
| **Requiere Broadcast** | ❌ |

**Request Body:**
```json
{
  "username": "required|string|max:50",
  "user_tag": "required|string|max:50|unique:users,user_tag",
  "first_name": "required|string|max:50",
  "last_name": "required|string|max:50",
  "email": "required|string|email|max:255|unique:users,email",
  "password": "required|string|min:8|confirmed",
  "password_confirmation": "required|string"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "user": { "uuid": "string", "username": "string", "user_tag": "string", ... },
    "token": "string"
  }
}
```

---

### AUTH-02: Login
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/auth/login` |
| **Frontend Service** | No existe servicio aún |
| **FormRequest existente** | `LoginRequest` ✅ (tiene reglas definidas) |
| **Estado** | ⚠️ NO IMPLEMENTADO EN CONTROLLER |
| **Requiere Broadcast** | ❌ |

**Request Body:**
```json
{
  "email": "required|email",
  "password": "required|string"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "user": { "uuid": "string", "username": "string", ... },
    "token": "string"
  }
}
```

---

### AUTH-03: Logout
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/auth/logout` |
| **Middleware** | `auth:sanctum` |
| **Estado** | ⚠️ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{ "status": "success", "data": null }
```

---

## 👤 USER — Usuario Autenticado

### USER-01: Obtener Usuario Actual
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/user` |
| **Frontend Service** | `UserService.getCurrentUser()` |
| **Frontend Hook** | `useCurrentUser()` → queryKey: `["current_user_v2"]` |
| **FormRequest** | No aplica (GET sin body) |
| **Estado** | 🟡 PARCIALMENTE IMPLEMENTADO — Existe la ruta en `api.php` pero solo retorna `$request->user()` básico. El frontend espera `club_uuids` dentro del objeto. |
| **Requiere Broadcast** | ❌ |

**Query Params:** Ninguno

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "username": "string",
    "user_tag": "string",
    "first_name": "string",
    "last_name": "string",
    "avatar_url": "string|null",
    "banner_url": "string|null",
    "bio": "string",
    "location": "string|null",
    "is_online": "boolean",
    "club_uuids": ["string"],
    "created_at": "string",
    "updated_at": "string"
  }
}
```

> [!IMPORTANT]
> El frontend usa `currentUser.club_uuids` para cargar los clubes del sidebar. Este campo NO es una columna directa del User — debe computarse via la relación `memberships` → `club_uuid`.

---

### USER-02: Obtener Perfil de Usuario Público
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/users/{user_uuid}` |
| **Frontend Service** | `UserService.getUser(user_uuid)` |
| **Frontend Hook** | `useUser(user_uuid)` → queryKey: `["user", user_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Route Params:**
- `user_uuid` — UUID del usuario target

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "username": "string",
    "user_tag": "string",
    "first_name": "string",
    "last_name": "string",
    "avatar_url": "string|null",
    "banner_url": "string|null",
    "bio": "string",
    "location": "string|null",
    "is_online": "boolean",
    "created_at": "string"
  }
}
```

---

### USER-03: Actualizar Perfil del Usuario Actual
| Campo | Valor |
|:---|:---|
| **Método** | `PATCH` |
| **Ruta** | `/api/user` |
| **Frontend Service** | `UserService.updateUser(updateData)` |
| **Frontend Hook** | `useMutateUser()` → optimistic update en `["current_user_v2"]` |
| **FormRequest existente** | `UpdateUserRequest` ⚠️ (VACÍO — `authorize()` retorna `false`, `rules()` vacío) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ (datos privados del usuario) |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "username": "sometimes|string|max:50",
  "first_name": "sometimes|string|max:50",
  "last_name": "sometimes|string|max:50",
  "bio": "sometimes|string|max:500|nullable",
  "avatar_url": "sometimes|url|nullable",
  "banner_url": "sometimes|url|nullable",
  "location": "sometimes|string|max:100|nullable",
  "current_password": "required_with:new_password|string",
  "new_password": "sometimes|string|min:8|confirmed"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": { "uuid": "string", "username": "string", ... }
}
```

---

### USER-04: Obtener Sesiones Activas
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/user/sessions` |
| **Frontend Service** | `UserService.getSessions()` |
| **Frontend Hook** | `useSessions()` → queryKey: `["user_sessions"]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "uuid": "string",
      "os": "string",
      "browser": "string",
      "ip": "string",
      "location": "string",
      "is_current": "boolean",
      "type": "desktop|mobile|tablet"
    }
  ]
}
```

---

### USER-05: Obtener Lista de Amigos (Paginada)
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/user/friends` |
| **Frontend Service** | `UserService.getFriendsPaginated(filter, searchQuery, pageParam)` |
| **Frontend Hook** | `useFriends(filter, searchQuery)` → queryKey: `["friends", filter, searchQuery]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `filter` | string | No | `all` (default), `online`, `pending` |
| `search` | string | No | Búsqueda por nombre/username |
| `cursor` | string | No | UUID del último amigo visible |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "username": "string",
      "display_name": "string",
      "avatar_url": "string|null",
      "is_online": "boolean",
      "friendship_uuid": "string"
    }
  ],
  "meta": { "next_cursor": "string|null" }
}
```

---

## 💬 CHAT — Mensajería en Canales de Club

### CHAT-01: Obtener Mensajes de Canal (Cursor Pagination)
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/channels/{channel_uuid}/messages` |
| **Frontend Service** | `ChatService.getMessages(channel_uuid, pageParam)` |
| **Frontend Hook** | `useChatMessages(channel_uuid)` → `useInfiniteQuery`, queryKey: `["chat_messages", channel_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ (es lectura) |

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `cursor` | string | No | UUID del mensaje más antiguo visible. Sin cursor = últimos mensajes |
| `limit` | integer | No | Default: `20`. Máximo: `50` |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "client_uuid": "string",
      "channel_uuid": "string",
      "sender_uuid": "string",
      "content": "string",
      "status": "sent",
      "parent_message_uuid": "string|null",
      "created_at": "string",
      "updated_at": "string",
      "user": {
        "uuid": "string",
        "username": "string",
        "avatar_url": "string|null"
      }
    }
  ],
  "meta": { "next_cursor": "string|null", "per_page": 20 }
}
```

> [!IMPORTANT]
> **Índice compuesto obligatorio** en PostgreSQL: `(channel_uuid, created_at)` para que la paginación por cursor no mate el rendimiento con volumen.

---

### CHAT-02: Enviar Mensaje a Canal
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/channels/{channel_uuid}/messages` |
| **Frontend Service** | `ChatService.sendMessage(channel_uuid, { content, client_uuid, parent_message_uuid })` |
| **Frontend Hook** | `useMutateChatMessages(channel_uuid)` → optimistic update con `client_uuid` como UUID temporal |
| **FormRequest existente** | `StoreChannelMessageRequest` ✅ (TIENE REGLAS DEFINIDAS) |
| **Estado** | ⚠️ FORM REQUEST LISTO — Falta controller y ruta |
| **Requiere Broadcast** | ✅ `MessageCreated` via Reverb (ShouldBroadcast + Queue) |

**Route Params:**
- `channel_uuid` — UUID del canal destino

**Request Body:**
```json
{
  "content": "required|string|max:4000",
  "client_uuid": "required|uuid",
  "parent_message_uuid": "nullable|uuid|exists:channel_messages,uuid"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string (server-generated)",
    "client_uuid": "string (el mismo que envió el frontend)",
    "channel_uuid": "string",
    "sender_uuid": "string",
    "content": "string",
    "status": "sent",
    "parent_message_uuid": "string|null",
    "created_at": "string",
    "user": {
      "uuid": "string",
      "username": "string",
      "avatar_url": "string|null"
    }
  }
}
```

> [!CAUTION]
> **Deduplicación**: La columna `client_uuid` en `channel_messages` DEBE ser `UNIQUE`. Si llega un duplicado, Laravel debe retornar el mensaje existente (idempotencia), NO crear uno nuevo.

> [!IMPORTANT]
> **Broadcast Payload Minimalista**: El evento WebSocket solo debe emitir `{ uuid, client_uuid, channel_uuid, sender_uuid, content, status, parent_message_uuid, created_at, user: { uuid, username, avatar_url } }`. El frontend usa `client_uuid` para deduplicar contra su optimistic update.

---

## 📩 CHAT — Mensajes Directos (DM)

### DM-01: Obtener Lista de Conversaciones DM (Paginada)
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/user/dm-conversations` |
| **Frontend Service** | `ChatService.getDMConversationsList(searchQuery, pageParam)` |
| **Frontend Hook** | `useDirectMessagesList(searchQuery)` → `useInfiniteQuery`, queryKey: `["dm_conversations", searchQuery]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `search` | string | No | Filtro por nombre de usuario |
| `cursor` | string | No | UUID de la última conversación visible |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "participant": {
        "uuid": "string",
        "username": "string",
        "avatar_url": "string|null",
        "is_online": "boolean"
      },
      "last_message": {
        "content": "string",
        "created_at": "string",
        "sender_uuid": "string"
      },
      "unread_count": "integer",
      "updated_at": "string"
    }
  ],
  "meta": { "next_cursor": "string|null" }
}
```

---

### DM-02: Obtener Detalle de Conversación DM
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/dm-conversations/{dm_conversation_uuid}` |
| **Frontend Service** | `ChatService.getDMConversation(chatUuid)` |
| **Frontend Hook** | `useDMConversation(dm_uuid)` → queryKey: `["dm_conversation", dm_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "participant": {
      "uuid": "string",
      "username": "string",
      "avatar_url": "string|null",
      "is_online": "boolean"
    },
    "created_at": "string"
  }
}
```

---

### DM-03: Obtener Mensajes de Conversación DM (Cursor Pagination)
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/dm-conversations/{dm_conversation_uuid}/messages` |
| **Frontend Service** | `ChatService.getDMMessages(dm_conversation_uuid, pageParam)` |
| **Frontend Hook** | `useDMChatMessages(dm_conversation_uuid)` → `useInfiniteQuery`, queryKey: `["dm_messages", dm_conversation_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `cursor` | string | No | UUID del mensaje más antiguo visible |
| `limit` | integer | No | Default: `20` |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "client_uuid": "string",
      "dm_conversation_uuid": "string",
      "sender_uuid": "string",
      "content": "string",
      "status": "sent",
      "parent_message_uuid": "string|null",
      "created_at": "string",
      "updated_at": "string",
      "user": {
        "uuid": "string",
        "username": "string",
        "avatar_url": "string|null"
      }
    }
  ],
  "meta": { "next_cursor": "string|null", "per_page": 20 }
}
```

---

### DM-04: Enviar Mensaje Directo
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/dm-conversations/{dm_conversation_uuid}/messages` |
| **Frontend Service** | `ChatService.sendDMMessage(dm_conversation_uuid, { content, client_uuid, parent_message_uuid })` |
| **Frontend Hook** | `useMutateDMChatMessages(dm_conversation_uuid)` → optimistic update |
| **FormRequest existente** | `StoreDmMessageRequest` ⚠️ (VACÍO — `authorize()` retorna `false`, `rules()` vacío) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ `DMMessageCreated` via Reverb |

**Request Body:**
```json
{
  "content": "required|string|max:4000",
  "client_uuid": "required|uuid",
  "parent_message_uuid": "nullable|uuid|exists:dm_messages,uuid"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "client_uuid": "string",
    "dm_conversation_uuid": "string",
    "sender_uuid": "string",
    "content": "string",
    "status": "sent",
    "parent_message_uuid": "string|null",
    "created_at": "string",
    "user": {
      "uuid": "string",
      "username": "string",
      "avatar_url": "string|null"
    }
  }
}
```

---

## 🏠 CLUB — Gestión de Clubes

### CLUB-01: Obtener Clubes del Usuario
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/user/clubs` |
| **Frontend Service** | `ClubService.getUserClubs(club_uuids)` |
| **Frontend Hook** | `useUserClubs(user_uuid, club_uuids)` → queryKey: `["user_clubs", user_uuid, club_uuids]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Nota:** El frontend actualmente pasa un array de `club_uuids` al servicio, pero en producción el backend debería inferir los clubes del usuario autenticado via la relación `memberships`. No se necesitan query params.

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "uuid": "string",
      "name": "string",
      "description": "string",
      "logo_url": "string|null",
      "banner_url": "string|null",
      "category_tag": "string",
      "owner_uuid": "string",
      "members_count": "integer",
      "online_count": "integer",
      "is_verified": "boolean",
      "created_at": "string"
    }
  ]
}
```

---

### CLUB-02: Obtener Detalle de un Club
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/clubs/{club_uuid}` |
| **Frontend Service** | `ClubService.getClubByUuid(club_uuid)` |
| **Frontend Hook** | `useClub(club_uuid)` → queryKey: `["club", club_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "name": "string",
    "description": "string",
    "logo_url": "string|null",
    "banner_url": "string|null",
    "category_tag": "string",
    "owner_uuid": "string",
    "members_count": "integer",
    "online_count": "integer",
    "is_verified": "boolean",
    "created_at": "string",
    "updated_at": "string"
  }
}
```

---

### CLUB-03: Crear un Club
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/clubs` |
| **Frontend Service** | `ClubService.createClub({ client_uuid, name, description, category_tag, logo_url, banner_url, owner_uuid })` |
| **Frontend Hook** | `useMutateCreateClub()` → optimistic update en `["user_clubs"]` y `["current_user_v2"]` |
| **FormRequest existente** | `StoreClubRequest` ⚠️ (VACÍO — `authorize()` retorna `false`, `rules()` vacío) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ (notificar al usuario en otros dispositivos) |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "name": "required|string|max:100",
  "description": "sometimes|string|max:500|nullable",
  "category_tag": "required|string|max:50",
  "logo_url": "sometimes|url|nullable",
  "banner_url": "sometimes|url|nullable"
}
```

> [!NOTE]
> `owner_uuid` NO debe enviarse en el body — se infiere del `$request->user()->uuid` en el controller. El frontend lo envía actualmente en mock pero en producción el backend lo asigna.

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "name": "string",
    "description": "string|null",
    "category_tag": "string",
    "logo_url": "string|null",
    "banner_url": "string|null",
    "owner_uuid": "string",
    "members_count": 1,
    "online_count": 1,
    "is_verified": false,
    "categories": [],
    "created_at": "string"
  }
}
```

---

### CLUB-04: Actualizar un Club
| Campo | Valor |
|:---|:---|
| **Método** | `PATCH` |
| **Ruta** | `/api/clubs/{club_uuid}` |
| **Frontend Service** | `ClubService.updateClub(club_uuid, payload)` |
| **Frontend Hook** | `useMutateClub()` → optimistic update en `["club", club_uuid]` |
| **FormRequest existente** | `UpdateClubRequest` ⚠️ (VACÍO — `authorize()` retorna `false`, `rules()` vacío) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ (todos los miembros ven el cambio) |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "name": "sometimes|string|max:100",
  "description": "sometimes|string|max:500|nullable",
  "category_tag": "sometimes|string|max:50",
  "logo_url": "sometimes|url|nullable",
  "banner_url": "sometimes|url|nullable"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": { "uuid": "string", "name": "string", ... }
}
```

---

### CLUB-05: Obtener Membresía de Usuario en Club
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/clubs/{club_uuid}/members/{user_uuid}` |
| **Frontend Service** | `ClubService.getMembership(club_uuid, user_uuid)` |
| **Frontend Hook** | `useClubMembership(club_uuid, user_uuid)` → queryKey: `["club_membership", club_uuid, user_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "club_uuid": "string",
    "roles_ids": ["string"]
  }
}
```

---

### CLUB-06: Obtener Miembros del Club (Paginado)
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/clubs/{club_uuid}/members` |
| **Frontend Service** | `ClubService.getMembers(club_uuid, pageParam)` |
| **Frontend Hook** | `useClubMembers(club_uuid)` → `useInfiniteQuery`, queryKey: `["club_members", club_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `cursor` | string | No | UUID del último miembro visible |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "username": "string",
      "display_name": "string",
      "avatar_url": "string|null",
      "roles_ids": ["string"],
      "is_online": "boolean"
    }
  ],
  "meta": { "club_uuid": "string", "next_cursor": "string|null" }
}
```

---

### CLUB-07: Obtener Usuarios Baneados del Club (Paginado)
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/clubs/{club_uuid}/bans` |
| **Frontend Service** | NO EXISTE EN CAPA DE SERVICIOS — el hook llama mock data directamente |
| **Frontend Hook** | `useGetBansUsers(club_uuid)` → `useInfiniteQuery`, queryKey: `["club_bans", club_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

> [!WARNING]
> Este hook viola la arquitectura: consume mock data directamente en el `queryFn` sin pasar por la capa de servicios. Cuando se implemente, se necesita crear `ClubService.getBannedUsers(club_uuid, pageParam)`.

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `cursor` | string | No | UUID del último ban visible |

**Response (200):**
```json
{
  "data": [
    {
      "ban_uuid": "string",
      "reason": "string",
      "banned_at": "string",
      "user": {
        "uuid": "string",
        "username": "string",
        "first_name": "string",
        "last_name": "string",
        "avatar_url": "string|null",
        "category_tag": "string"
      }
    }
  ],
  "meta": { "next_cursor": "string|null" }
}
```

---

## 📂 CATEGORIES — Categorías de Canales

### CAT-01: Obtener Categorías de un Club
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/clubs/{club_uuid}/categories` |
| **Frontend Service** | `ClubService.getCategories(club_uuid)` |
| **Frontend Hook** | `useClubCategories(club_uuid)` → queryKey: `["club_categories", club_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "uuid": "string",
      "club_uuid": "string",
      "name": "string",
      "sort_order": "integer",
      "is_private": "boolean",
      "channels": [
        {
          "uuid": "string",
          "category_uuid": "string",
          "name": "string",
          "description": "string|null",
          "type": "text|voice",
          "sort_order": "integer",
          "is_private": "boolean"
        }
      ]
    }
  ]
}
```

> [!NOTE]
> Las categorías se devuelven CON sus canales anidados. El frontend renderiza el sidebar completo de canales desde esta respuesta.

---

### CAT-02: Crear Categoría
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/clubs/{club_uuid}/categories` |
| **Frontend Service** | `ClubService.createCategory(club_uuid, { client_uuid, name, is_private })` |
| **Frontend Hook** | `useMutateCreateCategory(club_uuid)` → optimistic update en `["club_categories", club_uuid]` |
| **FormRequest existente** | `StoreClubCategoryRequest` ⚠️ (VACÍO) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ (estructura del club se actualiza para todos los miembros) |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "name": "required|string|max:100",
  "is_private": "sometimes|boolean"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "client_uuid": "string",
    "club_uuid": "string",
    "name": "string",
    "is_private": "boolean",
    "channels": [],
    "sort_order": "integer"
  }
}
```

---

### CAT-03: Editar Categoría
| Campo | Valor |
|:---|:---|
| **Método** | `PATCH` |
| **Ruta** | `/api/clubs/{club_uuid}/categories` |
| **Frontend Service** | `ClubService.editCategory(club_uuid, { category_uuid, name, is_private })` |
| **Frontend Hook** | `useMutateEditCategory(club_uuid)` → optimistic update |
| **FormRequest existente** | `UpdateClubCategoryRequest` ✅ |
| **Estado** | ✅ IMPLEMENTADO |
| **Requiere Broadcast** | ✅ |

> **Convención actualizada:** El `category_uuid` viaja en el **body** de la petición, no en la URL.

**Request Body:**
```json
{
  "category_uuid": "required|uuid",
  "name": "sometimes|string|max:100",
  "is_private": "sometimes|boolean"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": { "uuid": "string", "club_uuid": "string", "name": "string", "is_private": "boolean" }
}
```

---

## 📢 CHANNELS — Canales dentro de Categorías

### CH-01: Crear Canal
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/clubs/{club_uuid}/channels` |
| **Frontend Service** | `ClubService.createChannel(club_uuid, category_uuid, { client_uuid, name, type, is_private })` |
| **Frontend Hook** | `useMutateCreateChannel(club_uuid)` → optimistic update en `["club_categories", club_uuid]` |
| **FormRequest existente** | `StoreClubChannelRequest` ✅ |
| **Estado** | ✅ IMPLEMENTADO |
| **Requiere Broadcast** | ✅ |

> **Convención actualizada:** La ruta no incluye `{category_uuid}` en el path. El `category_uuid` viaja en el **body**.

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "category_uuid": "required|uuid",
  "name": "required|string|max:100",
  "type": "required|string|in:text,voice",
  "is_private": "sometimes|boolean"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "uuid": "string",
    "client_uuid": "string",
    "category_uuid": "string",
    "name": "string",
    "type": "text|voice",
    "is_private": "boolean",
    "sort_order": "integer"
  }
}
```

---

### CH-02: Editar Canal
| Campo | Valor |
|:---|:---|
| **Método** | `PATCH` |
| **Ruta** | `/api/clubs/{club_uuid}/channels/{channel_uuid}` |
| **Frontend Service** | `ClubService.editChannel(club_uuid, { channel_uuid, name, description, is_private })` |
| **Frontend Hook** | `useMutateEditChannel(club_uuid)` → optimistic update (action: `update`) |
| **FormRequest existente** | No existe — **NECESITA CREARSE** (`UpdateClubChannelRequest`) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "name": "sometimes|string|max:100",
  "description": "sometimes|string|max:500|nullable",
  "is_private": "sometimes|boolean"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": { "uuid": "string", "club_uuid": "string", "name": "string", "description": "string|null", "is_private": "boolean" }
}
```

---

### CH-03: Eliminar Canal
| Campo | Valor |
|:---|:---|
| **Método** | `DELETE` |
| **Ruta** | `/api/clubs/{club_uuid}/channels/{channel_uuid}` |
| **Frontend Service** | `ClubService.deleteChannel(club_uuid, channel_uuid)` |
| **Frontend Hook** | `useMutateEditChannel(club_uuid)` → optimistic update (action: `delete`) |
| **FormRequest** | No aplica (DELETE sin body) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ |

**Response (200):**
```json
{
  "status": "success",
  "data": { "uuid": "string", "club_uuid": "string", "action": "delete" }
}
```

---

## 🎭 ROLES — Gestión de Roles del Club

### ROLE-01: Obtener Roles del Club
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/clubs/{club_uuid}/roles` |
| **Frontend Service** | `ClubService.getRoles(club_uuid)` |
| **Frontend Hook** | `useClubRoles(club_uuid)` → queryKey: `["club_roles", club_uuid]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "uuid": "string",
      "club_uuid": "string",
      "name": "string",
      "color": "string",
      "is_fixed": "boolean",
      "permissions": {
        "manage_channels": "boolean",
        "manage_roles": "boolean",
        "manage_members": "boolean",
        "send_messages": "boolean",
        "manage_club": "boolean"
      }
    }
  ]
}
```

---

### ROLE-02: Crear Rol
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/clubs/{club_uuid}/roles` |
| **Frontend Service** | `ClubService.createRole(club_uuid, roleData)` |
| **Frontend Hook** | `useMutateClubRoles(club_uuid).mutateCreate` → optimistic update |
| **FormRequest existente** | No existe — **NECESITA CREARSE** (`StoreClubRoleRequest`) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "name": "required|string|max:50",
  "color": "required|string|max:7",
  "permissions": "required|array",
  "permissions.manage_channels": "required|boolean",
  "permissions.manage_roles": "required|boolean",
  "permissions.manage_members": "required|boolean",
  "permissions.send_messages": "required|boolean",
  "permissions.manage_club": "required|boolean"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": { "uuid": "string", "club_uuid": "string", "name": "string", "color": "string", "is_fixed": false, "permissions": { ... } }
}
```

---

### ROLE-03: Actualizar Rol
| Campo | Valor |
|:---|:---|
| **Método** | `PATCH` |
| **Ruta** | `/api/clubs/{club_uuid}/roles` |
| **Frontend Service** | `ClubService.updateRole(club_uuid, roleData)` |
| **Frontend Hook** | `useMutateClubRoles(club_uuid).mutateUpdate` → optimistic update |
| **FormRequest existente** | `UpdateClubRoleRequest` ✅ |
| **Estado** | ✅ IMPLEMENTADO |
| **Requiere Broadcast** | ✅ |

> **Convención actualizada:** El UUID del rol viaja en el campo `uuid` del **body**, no en la URL.

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "name": "sometimes|string|max:50",
  "color": "sometimes|string|max:7",
  "permissions": "sometimes|array",
  "permissions.manage_channels": "sometimes|boolean",
  "permissions.manage_roles": "sometimes|boolean",
  "permissions.manage_members": "sometimes|boolean",
  "permissions.send_messages": "sometimes|boolean",
  "permissions.manage_club": "sometimes|boolean"
}
```

---

### ROLE-04: Asignar Rol a Miembro
| Campo | Valor |
|:---|:---|
| **Método** | `POST` |
| **Ruta** | `/api/clubs/{club_uuid}/members/{user_uuid}/roles` |
| **Frontend Service** | `ClubService.assignRole(club_uuid, userUuid, roleUuid)` |
| **Frontend Hook** | `useMutateClubRoles(club_uuid).mutateAssign` → optimistic update en `["club_members", club_uuid]` |
| **FormRequest existente** | No existe — **NECESITA CREARSE** (`AssignClubRoleRequest`) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "role_uuid": "required|uuid|exists:club_roles,uuid"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": { "user_uuid": "string", "role_uuid": "string", "club_uuid": "string" }
}
```

---

## 🔔 NOTIFICATIONS — Solicitudes de Amistad

### NOTIF-01: Obtener Solicitudes de Amistad (Paginado)
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/user/friend-requests` |
| **Frontend Service** | `NotificationService.getFriendRequests(pageParam)` |
| **Frontend Hook** | `useMockFriendRequests()` → `useInfiniteQuery`, queryKey: `["friend_requests"]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `cursor` | string | No | UUID de la última solicitud visible |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "sender": {
        "uuid": "string",
        "username": "string",
        "avatar_url": "string|null",
        "category_tag": "string"
      },
      "status": "pending",
      "created_at": "string"
    }
  ],
  "meta": { "next_cursor": "string|null", "per_page": 10 }
}
```

---

### NOTIF-02: Responder a Solicitud de Amistad
| Campo | Valor |
|:---|:---|
| **Método** | `PATCH` |
| **Ruta** | `/api/user/friend-requests/{request_uuid}` |
| **Frontend Service** | `NotificationService.respondToFriendRequest(request_uuid, action)` |
| **Frontend Hook** | `useMutateFriendRequests()` → optimistic update en `["friend_requests"]`, invalida `["friends"]` si acepta |
| **FormRequest existente** | `StoreFriendshipRequest` ⚠️ (VACÍO) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ✅ (el otro usuario debe ver el cambio de estado) |

**Request Body:**
```json
{
  "client_uuid": "required|uuid",
  "action": "required|string|in:accept,decline"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": { "request_uuid": "string", "action": "accept|decline" }
}
```

---

## 🔍 DASHBOARD — Exploración y Búsqueda Global

### DASH-01: Obtener Datos de Exploración
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/explore` |
| **Frontend Service** | `DashboardService.getExploreData()` |
| **Frontend Hook** | `useDashboardData()` → queryKey: `["dashboard_explore"]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "featured_clubs": [
      {
        "uuid": "string",
        "name": "string",
        "description": "string",
        "logo_url": "string|null",
        "banner_url": "string|null",
        "members_count": "integer",
        "category_tag": "string",
        "is_verified": "boolean"
      }
    ],
    "categories": [
      {
        "title": "string",
        "category_tag": "string",
        "clubs": [ ... ]
      }
    ]
  }
}
```

---

### DASH-02: Búsqueda Global
| Campo | Valor |
|:---|:---|
| **Método** | `GET` |
| **Ruta** | `/api/search` |
| **Frontend Service** | `DashboardService.globalSearch(searchTerm, filterType, pageParam)` |
| **Frontend Hook** | `useSearchClubs(searchTerm, filterType)` → `useInfiniteQuery`, queryKey: `["global_search", searchTerm, filterType]` |
| **FormRequest** | No aplica (GET) |
| **Estado** | ❌ NO IMPLEMENTADO |
| **Requiere Broadcast** | ❌ |

**Query Params:**
| Param | Tipo | Requerido | Descripción |
|:---|:---|:---|:---|
| `q` | string | Sí | Término de búsqueda |
| `type` | string | No | `all` (default), `clubs`, `users` |
| `cursor` | string | No | UUID del último resultado |

**Response (200):**
```json
{
  "data": [
    {
      "uuid": "string",
      "_type": "club|user",
      "name": "string",
      "description": "string|null",
      "avatar_url": "string|null",
      "members_count": "integer (solo clubs)",
      "is_online": "boolean (solo users)"
    }
  ],
  "meta": { "next_cursor": "string|null" },
  "total_count": "integer"
}
```

---

## 📊 Resumen de Estado

### FormRequests Existentes vs Necesarios

| FormRequest | Estado | Acción Requerida |
|:---|:---|:---|
| `LoginRequest` | ✅ Tiene reglas | Conectar a controller |
| `StoreUserRequest` | ✅ Tiene reglas | Conectar a controller |
| `StoreChannelMessageRequest` | ✅ Tiene reglas | Conectar a controller |
| `StoreDmMessageRequest` | ⚠️ Vacío | Agregar reglas: `content`, `client_uuid`, `parent_message_uuid` |
| `StoreClubRequest` | ⚠️ Vacío | Agregar reglas: `client_uuid`, `name`, `description`, `category_tag`, `logo_url`, `banner_url` |
| `StoreClubCategoryRequest` | ⚠️ Vacío | Agregar reglas: `client_uuid`, `name`, `is_private` |
| `StoreClubChannelRequest` | ⚠️ Vacío | Agregar reglas: `client_uuid`, `name`, `type`, `is_private` |
| `StoreFriendshipRequest` | ⚠️ Vacío | Agregar reglas: `client_uuid`, `action` |
| `UpdateClubRequest` | ⚠️ Vacío | Agregar reglas: `client_uuid`, `name`, `description`, etc. |
| `UpdateUserRequest` | ⚠️ Vacío | Agregar reglas: `client_uuid`, `username`, `first_name`, etc. |
| `UpdateClubCategoryRequest` | ✅ Implementado | OK |
| `UpdateClubChannelRequest` | ✅ Implementado | OK |
| `StoreClubRoleRequest` | ✅ Implementado | OK |
| `UpdateClubRoleRequest` | ✅ Implementado | OK |
| `AssignClubRoleRequest` | ✅ Implementado | OK (sin `client_uuid`, usa `UNIQUE(club_member_uuid, role_uuid)`) |

### Controllers Necesarios

| Controller | Estado | Métodos Necesarios |
|:---|:---|:---|
| `AuthController` | ⚠️ Existe pero vacío | `register()`, `login()`, `logout()` |
| `UserController` | ❌ No existe | `show()`, `update()`, `sessions()`, `friends()` |
| `ClubController` | ❌ No existe | `index()`, `show()`, `store()`, `update()` |
| `ClubMemberController` | ❌ No existe | `index()`, `show()` (membership), `bans()` |
| `ClubCategoryController` | ❌ No existe | `index()`, `store()`, `update()` |
| `ClubChannelController` | ✅ Implementado | `index()`, `store()`, `update()`, `destroy()` |
| `ClubRoleController` | ❌ No existe | `index()`, `store()`, `update()` |
| `ClubMemberRoleController` | ✅ Implementado | `store()` (assign role) |
| `ChannelMessageController` | ❌ No existe | `index()`, `store()` |
| `DmConversationController` | ❌ No existe | `index()`, `show()` |
| `DmMessageController` | ❌ No existe | `index()`, `store()` |
| `FriendRequestController` | ❌ No existe | `index()`, `update()` (respond) |
| `ExploreController` | ❌ No existe | `index()` |
| `SearchController` | ❌ No existe | `index()` |

### Conteo Total de Endpoints

| Tipo | Cantidad |
|:---|:---|
| **GET (Queries)** | 17 |
| **POST (Mutations - Create)** | 8 |
| **PATCH (Mutations - Update)** | 5 |
| **DELETE (Mutations - Destroy)** | 1 |
| **TOTAL** | **31** |

### Endpoints que Requieren Broadcast (Reverb)

| Endpoint | Evento Broadcast |
|:---|:---|
| CHAT-02: Enviar mensaje a canal | `MessageCreated` |
| DM-04: Enviar mensaje directo | `DMMessageCreated` |
| CLUB-03: Crear club | `ClubCreated` |
| CLUB-04: Actualizar club | `ClubUpdated` |
| CAT-02: Crear categoría | `CategoryCreated` |
| CAT-03: Editar categoría | `CategoryUpdated` |
| CH-01: Crear canal | `ChannelCreated` |
| CH-02: Editar canal | `ChannelUpdated` |
| CH-03: Eliminar canal | `ChannelDeleted` |
| ROLE-02: Crear rol | `RoleCreated` |
| ROLE-03: Actualizar rol | `RoleUpdated` |
| ROLE-04: Asignar rol | `MemberRoleAssigned` |
| NOTIF-02: Responder solicitud | `FriendRequestResponded` |
