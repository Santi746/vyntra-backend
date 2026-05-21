# 🎨 Guía: API Resources (Formateadores de Respuesta JSON)

**Objetivo:** Crear los API Resources que transforman los modelos Eloquent (objetos PHP) en el JSON limpio, seguro y estable que espera el frontend de Vyntra.

> [!IMPORTANT]
> **Regla de Oro:** El frontend NUNCA debe recibir un modelo Eloquent en bruto. Siempre debe pasar por un API Resource. Esto protege contra la exposición accidental de campos sensibles (como `password`, `remember_token`) y da estabilidad al contrato con el frontend ante cambios en la BD.

---

## ¿Por qué son críticos para la escalabilidad?

Sin API Resources, si renombras una columna en PostgreSQL (ej: `cover_image_url` → `banner_url`), el frontend se rompe inmediatamente porque espera el nombre viejo. **Con un API Resource**, solo editas el Resource y el frontend nunca se entera del cambio.

---

## API Resources requeridos en Vyntra

| API Resource | Modelo que formatea | Usado en |
|---|---|---|
| `UserResource` | `User` | AuthController, UserController |
| `ClubResource` | `Club` | ClubController |
| `ClubCategoryResource` | `ClubCategory` | ClubCategoryController |
| `ClubChannelResource` | `ClubChannel` | ClubChannelController |
| `MessageResource` | `ChannelMessage` | ChannelMessageController |
| `DmConversationResource` | `DmConversation` | DmConversationController |
| `DmMessageResource` | `DmMessage` | DmMessageController |
| `NotificationResource` | `Notification` | NotificationController |
| `FriendshipResource` | `Friendship` | FriendshipController |

---

## Checklist de Implementación

### 1. Generar los archivos vacíos con Artisan
- [ ] Ejecutar los comandos:
  ```bash
  php artisan make:resource UserResource
  php artisan make:resource ClubResource
  php artisan make:resource ClubCategoryResource
  php artisan make:resource ClubChannelResource
  php artisan make:resource MessageResource
  php artisan make:resource DmConversationResource
  php artisan make:resource DmMessageResource
  php artisan make:resource NotificationResource
  php artisan make:resource FriendshipResource
  ```
  > Los archivos se crearán en `app/Http/Resources/`

---

### 2. Estructura interna de un API Resource (Patrón obligatorio)

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

### 3. Campos por Resource (Según el api_contract.md)

#### `UserResource`
- [ ] `uuid`, `username`, `user_tag`, `first_name`, `last_name`
- [ ] `avatar_url`, `banner_url`, `bio`, `location`
- [ ] `is_online` *(boolean, no string)*
- [ ] `created_at` *(ISO 8601)*
- [ ] ❌ Omitir: `password`, `remember_token`, `email_verified_at`

#### `ClubResource`
- [ ] `uuid`, `name`, `description`, `category_tag`
- [ ] `avatar_url`, `cover_image_url`
- [ ] `owner_uuid`
- [ ] `created_at`
- [ ] Relación anidada (opcional): `owner` → `new UserResource($this->whenLoaded('clubOwner'))`

#### `MessageResource` (Canal y DM comparten estructura similar)
- [ ] `uuid`, `client_uuid`, `content`, `status`
- [ ] `parent_message_uuid`
- [ ] `created_at`, `updated_at`
- [ ] Relación anidada: `sender` → `new UserResource($this->whenLoaded('sender'))`

#### `NotificationResource`
- [ ] `uuid`, `type`, `is_read`
- [ ] `data` *(el campo JSON de la notificación)*
- [ ] `created_at`

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
✅ 9 API Resources creados en app/Http/Resources/
✅ Ningún campo sensible (password, remember_token) se expone en ningún Resource
✅ Todas las relaciones anidadas usan whenLoaded() para prevenir N+1
✅ Fechas devueltas en formato ISO 8601
✅ Booleanos devueltos como true/false (no "0"/"1")
```

---

## Siguiente paso
➡️ [05_routes_guide.md](./05_routes_guide.md)
