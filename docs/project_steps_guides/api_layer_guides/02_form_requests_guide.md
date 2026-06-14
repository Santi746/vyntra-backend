# 📋 Guía: Form Requests (Validadores de Entrada) ✅

> **Completado.** 18 Form Requests creados, validación exhaustiva, mensajes en español.

**Objetivo:** Crear los Form Requests que actúan como porteros de seguridad. Cada petición de escritura (POST, PUT, PATCH) debe pasar por uno antes de llegar al Controller.

> [!IMPORTANT]
> **Regla de Oro:** La validación NUNCA va en el Controller. Si validas dentro del Controller, estás mezclando responsabilidades y tu código se vuelve imposible de mantener. Los Form Requests son la única capa que valida datos de entrada.

---

## ¿Qué son y cómo funcionan?

Cuando el frontend envía una petición con datos (ej: nombre del club, contenido del mensaje), el Form Request intercepta esa petición antes de que llegue al Controller y verifica que todos los campos cumplan las reglas definidas.

- Si los datos **son válidos** → El Controller recibe los datos limpios mediante `$request->validated()`
- Si los datos **son inválidos** → Laravel devuelve automáticamente un error `422 Unprocessable Content` con la lista de errores en JSON, sin ejecutar el Controller

---

## Form Requests requeridos en Vyntra

| Form Request | Usado en | Protege |
|---|---|---|
| `StoreUserRequest` | `AuthController@register` | Registro de usuario |
| `LoginRequest` | `AuthController@login` | Autenticación |
| `UpdateUserRequest` | `UserController@update` | Edición de perfil |
| `StoreClubRequest` | `ClubController@store` | Creación de club |
| `UpdateClubRequest` | `ClubController@update` | Edición de club |
| `StoreClubCategoryRequest` | `ClubCategoryController@store` | Creación de categoría |
| `UpdateClubCategoryRequest` | `ClubCategoryController@update` | Edición de categoría |
| `StoreClubChannelRequest` | `ClubChannelController@store` | Creación de canal |
| `UpdateClubChannelRequest` | `ClubChannelController@update` | Edición de canal |
| `StoreClubRoleRequest` | `ClubRoleController@store` | Creación de rol |
| `UpdateClubRoleRequest` | `ClubRoleController@update` | Edición de rol |
| `AssignClubRoleRequest` | `ClubMemberController@assignRole` | Asignación de rol a miembro |
| `StoreChannelMessageRequest` | `ChannelMessageController@store` | Envío de mensaje en canal |
| `StoreDmMessageRequest` | `DmMessageController@store` | Envío de mensaje privado |
| `RespondFriendshipRequest` | `FriendshipController@respond` | Responder a solicitud de amistad |

---

## Checklist de Implementación

### 1. Generar los archivos vacíos con Artisan
- [ ] Ejecutar los comandos:
  ```bash
  php artisan make:request StoreUserRequest
  php artisan make:request LoginRequest
  php artisan make:request UpdateUserRequest
  php artisan make:request StoreClubRequest
  php artisan make:request UpdateClubRequest
  php artisan make:request StoreClubCategoryRequest
  php artisan make:request UpdateClubCategoryRequest
  php artisan make:request StoreClubChannelRequest
  php artisan make:request UpdateClubChannelRequest
  php artisan make:request StoreClubRoleRequest
  php artisan make:request UpdateClubRoleRequest
  php artisan make:request AssignClubRoleRequest
  php artisan make:request StoreChannelMessageRequest
  php artisan make:request StoreDmMessageRequest
  php artisan make:request RespondFriendshipRequest
  ```
  > Los archivos se crearán en `app/Http/Requests/`

---

### 2. Estructura interna de un Form Request (Patrón obligatorio)

Cada Form Request generado tiene dos métodos que debes completar:

```php
class StoreChannelMessageRequest extends FormRequest
{
    /**
     * ¿Quién está autorizado a hacer esta petición?
     * Por ahora: cualquier usuario autenticado (true).
     * Más adelante: verificar que el usuario sea miembro del canal.
     */
    public function authorize(): bool
    {
        return true; // La autorización granular va en Policies (paso futuro)
    }

    /**
     * Reglas de validación de los campos del body de la petición.
     */
    public function rules(): array
    {
        return [
            'content'     => ['required', 'string', 'max:4000'],
            'client_uuid' => ['required', 'uuid'],
            'parent_message_uuid' => ['nullable', 'uuid', 'exists:channel_messages,uuid'],
        ];
    }
}
```

---

### 3. Reglas de validación críticas por Form Request

#### `StoreUserRequest` (Registro)
- [ ] `username` → required, string, max:50
- [ ] `user_tag` → required, string, max:50, unique:users,user_tag
- [ ] `first_name` → required, string, max:50
- [ ] `last_name` → required, string, max:50
- [ ] `email` → required, email, max:255, unique:users,email
- [ ] `password` → required, string, min:8, confirmed *(el frontend envía `password_confirmation`)*

#### `LoginRequest` (Autenticación)
- [ ] `email` → required, email
- [ ] `password` → required, string

#### `StoreClubRequest` (Crear Club)
- [ ] `client_uuid` → required, uuid *(idempotencia obligatoria)*
- [ ] `name` → required, string, max:100, unique:clubs,name
- [ ] `description` → nullable, string, max:500
- [ ] `category_tag` → required, string, in:gaming,music,programming,anime,...

#### `UpdateClubRequest` (Edición de Club)
- [ ] `name` → sometimes, string, max:100
- [ ] `description` → sometimes, string, max:500, nullable
- [ ] `category_tag` → sometimes, string, max:50
- [ ] `logo_url` → sometimes, url, nullable
- [ ] `banner_url` → sometimes, url, nullable

#### `StoreClubCategoryRequest` (Crear Categoría)
- [ ] `client_uuid` → required, uuid *(idempotencia obligatoria)*
- [ ] `name` → required, string, max:100
- [ ] `is_private` → sometimes, boolean

#### `UpdateClubCategoryRequest` (Editar Categoría)
- [ ] `name` → sometimes, string, max:100
- [ ] `is_private` → sometimes, boolean

#### `StoreClubChannelRequest` (Crear Canal)
- [ ] `client_uuid` → required, uuid *(idempotencia obligatoria)*
- [ ] `name` → required, string, max:100
- [ ] `type` → required, string, in:text,voice
- [ ] `is_private` → sometimes, boolean

#### `UpdateClubChannelRequest` (Editar Canal)
- [ ] `name` → sometimes, string, max:100
- [ ] `description` → sometimes, string, max:500, nullable
- [ ] `is_private` → sometimes, boolean

#### `StoreClubRoleRequest` (Crear Rol)
- [ ] `client_uuid` → required, uuid *(idempotencia obligatoria)*
- [ ] `name` → required, string, max:50
- [ ] `color` → required, string, regex:/^#[a-fA-F0-9]{6}$/i *(ej. #FFFFFF)*
- [ ] `permissions` → required, array
- [ ] `permissions.manage_channels` → required, boolean
- [ ] `permissions.manage_roles` → required, boolean
- [ ] `permissions.manage_members` → required, boolean
- [ ] `permissions.send_messages` → required, boolean
- [ ] `permissions.manage_club` → required, boolean

#### `UpdateClubRoleRequest` (Actualizar Rol)
- [ ] `name` → sometimes, string, max:50
- [ ] `color` → sometimes, string, regex:/^#[a-fA-F0-9]{6}$/i
- [ ] `permissions` → sometimes, array
- [ ] `permissions.manage_channels` → sometimes, boolean
- [ ] `permissions.manage_roles` → sometimes, boolean
- [ ] `permissions.manage_members` → sometimes, boolean
- [ ] `permissions.send_messages` → sometimes, boolean
- [ ] `permissions.manage_club` → sometimes, boolean

#### `AssignClubRoleRequest` (Asignar Rol a Miembro)
- [ ] `client_uuid` → sometimes, uuid *(pivot Many-to-Many)*
- [ ] `role_uuid` → required, uuid, exists:club_roles,uuid

#### `StoreChannelMessageRequest` (Enviar Mensaje)
- [ ] `content` → required, string, max:4000
- [ ] `client_uuid` → required, uuid *(idempotencia obligatoria según arquitectura)*
- [ ] `parent_message_uuid` → nullable, uuid, exists:channel_messages,uuid

#### `StoreDmMessageRequest` (Mensaje Privado)
- [ ] `content` → required, string, max:4000
- [ ] `client_uuid` → required, uuid
- [ ] `parent_message_uuid` → nullable, uuid, exists:dm_messages,uuid

---

### 4. Mensajes de error personalizados (Opcional pero recomendado)

Puedes personalizar los mensajes de error en español añadiendo el método `messages()`:

```php
public function messages(): array
{
    return [
        'content.required' => 'El contenido del mensaje no puede estar vacío.',
        'client_uuid.required' => 'Se requiere un identificador único de cliente.',
        'client_uuid.uuid' => 'El identificador de cliente debe ser un UUID válido.',
    ];
}
```

---

## Resultado esperado al finalizar

```
✅ 15 Form Requests creados en app/Http/Requests/
✅ Cada Form Request tiene authorize() y rules() completos
✅ Campos críticos como client_uuid y UUIDs foráneos validados correctamente
✅ Ninguna validación existe dentro de los Controllers
```

---

## Siguiente paso
➡️ [03_api_resources_guide.md](./03_api_resources_guide.md)
