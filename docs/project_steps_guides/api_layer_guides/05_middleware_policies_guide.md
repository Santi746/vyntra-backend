# 🛡️ Middleware + Policies — Reporte final de implementación ✅

> **Completado.** 9 policies + bitwise permissions + filtrado is_private + anti-privilege-escalation + tests.
>
> Estado actual después del paso 05. La guía ejecutable está en `05_middleware_policies_guide.md` original.
> Este archivo refleja el estado final del código.

---

## 1. Policies creadas (9 total)

| Policy | Modelo | Métodos | Registrada en AppServiceProvider |
|---|---|---|---|
| `ClubPolicy` | `Club` | `view`, `viewPrivateChannels`, `update`, `delete` | ✅ |
| `ChannelMessagePolicy` | `ChannelMessage` | `viewAny`, `create` | ✅ |
| `ClubCategoryPolicy` | `ClubCategory` | `viewAny`, `create`, `update`, `delete` | ✅ |
| `ClubChannelPolicy` | `ClubChannel` | `viewAny`, `create`, `update`, `delete` | ✅ |
| `ClubRolePolicy` | `ClubRole` | `viewAny`, `create`, `update`, `delete` | ✅ |
| `ClubMemberPolicy` | `ClubMember` | `viewAny`, `delete` | ✅ |
| `ClubMemberRolePolicy` | `ClubMemberRole` | `create` | ✅ |
| `DmConversationPolicy` | `DmConversation` | `view`, `create` | ✅ |
| `NotificationPolicy` | `Notification` | `update` | ✅ |

---

## 2. Verificaciones inline reemplazadas

| Controller | Método | Antes | Después |
|---|---|---|---|
| `NotificationController` | markAsRead | `abort_if($n->user_uuid !== $user->uuid, 403)` | `Gate::authorize('update', $notification)` |
| `DmConversationController` | show | `abort_unless(...)` | `Gate::authorize('view', $dmConversation)` |

---

## 3. Gate::authorize() añadidos donde faltaban

| Controller | Método | Gate |
|---|---|---|
| `ClubController` | show | `Gate::authorize('view', $club)` |
| `DmMessageController` | index | `Gate::authorize('view', $dmConversation)` |
| `DmMessageController` | store | `Gate::authorize('create', $dmConversation)` |
| `ClubCategoryController` | index, store | `viewAny` + `create` |
| `ClubChannelController` | index, store | `viewAny` + `create` |
| `ClubRoleController` | index, store, update, destroy | `viewAny` + `create` + `update` + `delete` |
| `ClubMemberController` | index, destroy | `viewAny` + `delete` |
| `ClubMemberRoleController` | store | `create` |

---

## 4. Nuevos endpoints

| Ruta | Método | Descripción |
|---|---|---|
| `GET /api/clubs/{club}/preview` | `ClubController@preview` | Preview público del club (única excepción sin membresía) |

---

## 5. Filtrado de is_private

Canales y categorías con `is_private=true` se filtran según el permiso `VIEW_CHANNELS`:
- **Owner** y **ADMINISTRATOR** → ven todos (privados y públicos)
- **Usuarios con rol que tenga `VIEW_CHANNELS`** → ven todos
- **Resto** → solo ven canales/categorías públicas

El filtrado ocurre en **backend** (no en frontend), evitando leak de información.

---

## 6. Anti-privilege-escalation en ClubRolePolicy

La policy `ClubRolePolicy` tiene un helper privado `outranks()` que verifica:
1. Owner → pasa todo
2. ADMINISTRATOR → pasa todo
3. El usuario debe tener `MANAGE_ROLES`
4. El usuario no puede editar/borrar un rol cuyos permisos excedan los suyos

Esto previene que un admin con `MANAGE_ROLES` se otorgue permisos superiores (como `MANAGE_CLUB` o `ADMINISTRATOR`).

---

## 7. Tests reescritos

4 archivos de tests reescritos con patrón HTTP puro:

| Archivo | Tests | Patrón |
|---|---|---|
| `NotificationControllerTest` | 7 | `actingAs sanctum` + `getJson/patchJson` |
| `DmConversationControllerTest` | 10 | `actingAs sanctum` + `getJson/postJson` |
| `DmMessageControllerTest` | 8 | `actingAs sanctum` + `getJson/postJson` |
| `ChannelMessageControllerTest` | 7 | `actingAs sanctum` + `getJson/postJson` con setup completo |

---

## 8. Documentación actualizada

| Documento | Cambio |
|---|---|
| `docs/api_contract.md` | Permisos documentados como integer (bitmask) con tabla de 16 bits. |
| `docs/api_requests_manifest.md` | ROLE-02 y ROLE-03: `permissions` como integer. CLUB-02b (preview) añadido. CLUB-02 anotado con "Requiere Membresía". |

---

## 9. Frontend

| Archivo | Cambio |
|---|---|
| `src/services/club.service.js` | Añadido `getClubPreview(club_uuid)` con JSDoc de excepción arquitectónica. |
| `src/features/clubs/components/atoms/ClubChannel.jsx` | Acepta prop `is_private`, renderiza `<Lock />` de lucide-react. |
| `src/features/clubs/components/molecules/ClubCategory.jsx` | Renderiza `<Lock />` cuando `is_private=true`. Pasa `is_private` a `ClubChannel`. |

---

## 10. Resultado de tests

```
76/76 tests pasan en archivos tocados. 0 regresiones.
```

Nota: 35 tests pre-existentes en otros controladores (Friendship, Search, Explore) continúan fallando con los mismos errores anteriores.
