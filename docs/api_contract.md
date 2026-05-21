# 📑 Contrato de Interfaz API - Proyecto Vyne

Este documento define el esquema de datos obligatorio para la comunicación entre el Backend (Laravel) y el Frontend (Next.js/React Query). Este contrato es la fuente de verdad absoluta para evitar desincronizaciones.

## 1. Reglas Globales de Comunicación
- **Formato**: JSON.
- **Naming Convention**: `snake_case` para todos los atributos (estándar de PostgreSQL y Laravel).
- **Identificadores**: Siempre usar `uuid` (string). Nunca exponer IDs incrementales numéricos.
- **Fechas**: Strings en formato ISO 8601 (`YYYY-MM-DDTHH:mm:ss.sssZ`).
- **Deduplicación**: Todas las mutaciones de creación requieren un `client_uuid` generado en el frontend para evitar duplicados en tiempo real.

---

## 2. Esquema de Entidades (Normalizado)

### A. Usuario (`User`)
Representa el perfil de un usuario en el sistema.
```json
{
  "uuid": "string",
  "username": "string",
  "category_tag": "string",
  "first_name": "string",
  "last_name": "string",
  "avatar_url": "string|null",
  "banner_url": "string|null",
  "bio": "string",
  "location": "string|null",
  "is_online": "boolean",
  "created_at": "string",
  "updated_at": "string"
}
```

### B. Mensaje de Chat (`ChatMessage`)
Utilizado tanto para canales de clubes como para mensajes directos.
```json
{
  "uuid": "string",
  "client_uuid": "string",
  "channel_uuid": "string|null",
  "dm_conversation_uuid": "string|null",
  "sender_uuid": "string",
  "content": "string",
  "status": "sent|sending|error",
  "parent_message_uuid": "string|null",
  "created_at": "string",
  "updated_at": "string",
  "user": {
    "uuid": "string",
    "username": "string",
    "avatar_url": "string"
  }
}
```

### C. Club y Estructura
- **Club**: `{ uuid, name, description, avatar_url, banner_url, owner_uuid, category_tag, created_at, updated_at }`
- **Category**: `{ uuid, club_uuid, name, sort_order, is_private, created_at, updated_at }`
- **Channel**: `{ uuid, category_uuid, name, description, type, sort_order, is_private, created_at, updated_at }`

---

## 3. Estructura de Respuestas (Standard Envelopes)

### Respuesta Simple (Single Object)
```json
{
  "status": "success",
  "data": { ... }
}
```

### Respuesta Pagidana (Cursor-based)
Obligatorio para mensajes y listas largas para asegurar escalabilidad.
```json
{
  "data": [ ... ],
  "meta": {
    "next_cursor": "string|null",
    "per_page": "number"
  }
}
```

### Errores de Validación (422 Unprocessable Entity)
```json
{
  "status": "error",
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["ErrorMessage"]
  }
}
```

---

## 4. Protocolo de WebSockets (Reverb)
Los eventos emitidos por el servidor DEBEN contener el objeto de data idéntico al que devuelve la API REST para permitir actualizaciones de caché transparentes.

---

## 5. Mapeo de Servicios y Endpoints (Referencia Técnica)

Este mapeo asegura que el Frontend (Servicios) y el Backend (Laravel) hablen el mismo lenguaje en términos de rutas y lógica.

| Servicio | Método Principal | Ruta API Estimada | Comportamiento / WebSocket |
| :--- | :--- | :--- | :--- |
| **`ChatService`** | `getMessages` | `GET /channels/{id}/messages` | Paginación por cursor obligatoria. |
| | `sendMessage` | `POST /channels/{id}/messages` | Dispara `MessageCreated` vía Reverb. |
| **`ClubService`** | `getMembership` | `GET /clubs/{id}/members/{u_id}` | Retorna roles del usuario en el club. |
| | `getMembers` | `GET /clubs/{id}/members` | Lista de miembros con roles anidados. |
| | `createChannel` | `POST /clubs/{id}/channels` | Notifica actualización de estructura. |
| **`UserService`** | `getCurrentUser` | `GET /user` | Perfil del usuario autenticado. |
| | `getFriends` | `GET /user/friends` | Lista de relaciones de amistad. |
| **`DashboardService`**| `globalSearch` | `GET /search` | Búsqueda indexada de clubes y usuarios. |
| **`NotificationService`**| `getFriendRequests`| `GET /user/requests` | Solicitudes de amistad entrantes. |

> [!TIP]
> Todos los servicios deben inyectar el token `Bearer` en las cabeceras `Authorization` una vez que el sistema de autenticación real esté activo.
