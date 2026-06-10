# 🎨 Guía: API Resources (Formateadores de Respuesta JSON)

**Objetivo:** Crear los API Resources que transforman los modelos Eloquent (objetos PHP) en el JSON limpio, seguro y estable que espera el frontend de Vyntra.

> [!IMPORTANT]
> **Regla de Oro:** El frontend NUNCA debe recibir un modelo Eloquent en bruto. Siempre debe pasar por un API Resource. Esto protege contra la exposición accidental de campos sensibles (como `password`, `remember_token`) y da estabilidad al contrato con el frontend ante cambios en la BD.

---

## ¿Por qué son críticos para la escalabilidad?

Sin API Resources, si renombras una columna en PostgreSQL (ej: `cover_image_url` → `banner_url`), el frontend se rompe inmediatamente porque espera el nombre viejo. **Con un API Resource**, solo editas el Resource y el frontend nunca se entera del cambio.

---

## API Resources requeridos en Vyntra

| API Resource | Modelo/Origen que formatea | Usado en |
|---|---|---|
| `UserResource` | `User` | AuthController, UserController |
| `ClubResource` | `Club` | ClubController |
| `ClubCategoryResource` | `ClubCategory` | ClubCategoryController |
| `ClubChannelResource` | `ClubChannel` | ClubChannelController |
| `ClubMemberResource` | `ClubMember` | ClubMemberController |
| `MessageResource` | `ChannelMessage` | ChannelMessageController |
| `DmConversationResource` | `DmConversation` | DmConversationController |
| `DmMessageResource` | `DmMessage` | DmMessageController |
| `NotificationResource` | `Notification` | NotificationController |
| `FriendshipResource` | `Friendship` | FriendshipController |
| `ClubRoleResource` | `ClubRole` | ClubRoleController |
| `SessionResource` | `PersonalAccessToken` / Datos de Sesión | UserController |

---

## Checklist de Implementación

### 1. Generar los archivos vacíos con Artisan
- [ ] Ejecutar los comandos:
  ```bash
  php artisan make:resource UserResource
  php artisan make:resource ClubResource
  php artisan make:resource ClubCategoryResource
  php artisan make:resource ClubChannelResource
  php artisan make:resource ClubMemberResource
  php artisan make:resource MessageResource
  php artisan make:resource DmConversationResource
  php artisan make:resource DmMessageResource
  php artisan make:resource NotificationResource
  php artisan make:resource FriendshipResource
  php artisan make:resource ClubRoleResource
  php artisan make:resource SessionResource
  ```
  > Los archivos se crearán en `app/Http/Resources/`

---

## 2. Estructura interna de un API Resource (Patrón obligatorio)

Un API Resource tiene un único método `toArray()` que define exactamente qué campos se exponen y con qué nombre:

```php
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Campos del modelo ChannelMessage
            'uuid'                => $this->uuid,
            'client_uuid'         => $this->client_uuid,
            'content'             => $this->content,
            'status'              => $this->status,
            'parent_message_uuid' => $this->parent_message_uuid,
            'created_at'          => $this->created_at->toISOString(),
            'updated_at'          => $this->updated_at->toISOString(),

            // Relación anidada: incluir datos del remitente
            // whenLoaded() evita el error N+1: solo incluye los datos si ya se
            // cargaron con ->with('sender') en el Controller. Si no, devuelve null.
            'sender' => new UserResource($this->whenLoaded('sender')),
        ];
    }
}
```

---

### 3. Campos por Resource (Según el api_contract.md y api_requests_manifest.md)

#### `UserResource`
- [ ] `uuid`, `username`, `user_tag`, `first_name`, `last_name`
- [ ] `avatar_url`, `banner_url`, `bio`, `location`
- [ ] `is_online` *(boolean, no string)*
- [ ] `created_at` y `updated_at` *(ISO 8601)*
- [ ] `club_uuids` *(array de strings, obtenido condicionalmente de memberships)*
  ```php
  'club_uuids' => $this->whenLoaded('memberships', function() {
      return $this->memberships->pluck('club_uuid');
  }),
  ```
- [ ] ❌ Omitir: `password`, `remember_token`, `email_verified_at`

#### `ClubResource`
- [ ] `uuid`, `name`, `description`, `category_tag`
- [ ] `logo_url`, `banner_url` *(corregidos según el manifesto de peticiones, no cover_image_url)*
- [ ] `owner_uuid`
- [ ] `members_count` y `online_count` *(conteos dinámicos o agregados)*
- [ ] `is_verified` *(boolean)*
- [ ] `created_at` y `updated_at` *(ISO 8601)*
- [ ] Relación anidada: `owner_uuid` → `new UserResource($this->whenLoaded('owner'))`

#### `ClubCategoryResource`
- [ ] `uuid`, `club_uuid`, `name`, `sort_order`
- [ ] `is_private` *(boolean)*
- [ ] Relación anidada: `channels` → `ClubChannelResource::collection($this->whenLoaded('channels'))`

#### `ClubChannelResource`
- [ ] `uuid`, `category_uuid`, `name`, `description`, `type` *(text o voice)*
- [ ] `sort_order`
- [ ] `is_private` *(boolean)*

#### `MessageResource` (Canal de Club)
- [ ] `uuid`, `client_uuid`, `channel_uuid`, `sender_uuid`, `content`, `status`
- [ ] `parent_message_uuid`
- [ ] `created_at` y `updated_at` *(ISO 8601)*
- [ ] Relación anidada: `user` (remitente) → `new UserResource($this->whenLoaded('sender'))`

#### `DmConversationResource`
- [ ] `uuid`, `created_at`, `updated_at`
- [ ] `participant` → `new UserResource($this->when($this->relationLoaded('user1') || $this->relationLoaded('user2'), function() { ... }))` (debe retornar el otro participante de la conversación que no sea el usuario autenticado)
- [ ] `last_message` → `new DmMessageResource($this->whenLoaded('lastMessage'))`
- [ ] `unread_count` *(int)*

#### `DmMessageResource`
- [ ] `uuid`, `client_uuid`, `dm_conversation_uuid`, `sender_uuid`, `content`, `status`
- [ ] `parent_message_uuid`
- [ ] `created_at` y `updated_at` *(ISO 8601)*
- [ ] Relación anidada: `user` → `new UserResource($this->whenLoaded('sender'))`

#### `NotificationResource` (Solicitudes de amistad / Notificaciones)
- [ ] `uuid`, `type`, `is_read`, `data`
- [ ] `created_at` y `updated_at` *(ISO 8601)*

#### `FriendshipResource` (Para listar amistades y solicitudes)
- [ ] `uuid` *(friendship_uuid)*, `status`
- [ ] `friend` (o sender/recipient dependiendo del rol del usuario autenticado) → `new UserResource($this->whenLoaded('friend'))`
- [ ] Para `USER-05` (Lista de amigos): la respuesta se puede aplanar en el Resource para retornar la estructura esperada:
  ```php
  return [
      'uuid' => $friend->uuid,
      'username' => $friend->username,
      'display_name' => $friend->first_name . ' ' . $friend->last_name,
      'avatar_url' => $friend->avatar_url,
      'is_online' => $friend->is_online,
      'friendship_uuid' => $this->uuid
  ];
  ```

#### `ClubRoleResource`
- [ ] `uuid`, `club_uuid`, `name`, `color`
- [ ] `permissions` *(objeto/array asociativo de booleanos)*:
  - `manage_channels`, `manage_roles`, `manage_members`, `send_messages`, `manage_club`
- [ ] `is_fixed` *(boolean)*

#### `SessionResource` (Sesiones activas - USER-04)
- [ ] `uuid` *(ID del token)*, `os`, `browser`, `ip`, `location`, `is_current`, `type`

---

### 4. El poder de `whenLoaded()` — Evitar el problema N+1

> [!CAUTION]
> **El problema N+1** es el error de rendimiento más común en backends ORM. Ocurre cuando cargas 50 mensajes y por cada mensaje el sistema hace una consulta SQL adicional para obtener el remitente, resultando en **51 consultas** en vez de 2.

**La solución es siempre usar `->with('relation')` en el Controller:**
```php
// ✅ CORRECTO: 2 consultas SQL (mensajes + senders en un JOIN)
ChannelMessage::with('sender')->where(...)->get();

// ❌ INCORRECTO: 1 + N consultas SQL (una por cada mensaje)
ChannelMessage::where(...)->get(); // Y luego el Resource llama $this->sender
```

El método `$this->whenLoaded('sender')` en el Resource es el guardián que garantiza que esto sea consistente: si el Controller olvidó cargar la relación, devuelve `null` en lugar de disparar una consulta extra inesperada.

---

## Resultado esperado al finalizar

```
✅ 11 API Resources creados en app/Http/Resources/
✅ Ningún campo sensible (password, remember_token) se expone en ningún Resource
✅ Todas las relaciones anidadas usan whenLoaded() para prevenir N+1
✅ Fechas devueltas en formato ISO 8601
✅ Booleanos devueltos como true/false (no "0"/"1")
```

---

## Siguiente paso
➡️ [04_controllers_guide.md](./04_controllers_guide.md)
