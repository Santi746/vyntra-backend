# 🛡️ Middleware + Policies — Checklist de implementación

> Basado en auditoría real del código. Solo acciones, sin teoría.

---

## [ ] 1. Crear las Policies

```bash
php artisan make:policy ClubPolicy
php artisan make:policy ChannelMessagePolicy
php artisan make:policy ClubCategoryPolicy
php artisan make:policy ClubChannelPolicy
php artisan make:policy ClubMemberPolicy
php artisan make:policy ClubMemberRolePolicy
php artisan make:policy NotificationPolicy
php artisan make:policy DmConversationPolicy
```

---

## [ ] 2. Escribir la lógica de cada Policy

### ClubPolicy
| Método | Regla |
|---|---|
| `view(User $user, Club $club)` | `$club->members()->where('user_uuid', $user->uuid)->exists()` |
| `create(User $user)` | `true` |
| `update(User $user, Club $club)` | `$club->owner_uuid === $user->uuid` |
| `delete(User $user, Club $club)` | `$club->owner_uuid === $user->uuid` |

### ChannelMessagePolicy
| Método | Regla |
|---|---|
| `viewAny(User $user, ClubChannel $channel)` | `ClubMember::where('user_uuid', $user->uuid)->where('club_uuid', $channel->category->club_uuid)->exists()` |
| `create(User $user, ClubChannel $channel)` | `ClubMember::where('user_uuid', $user->uuid)->where('club_uuid', $channel->category->club_uuid)->exists()` |

### ClubCategoryPolicy
> Protege que solo el owner del club edite/elimine categorías.
| Método | Regla |
|---|---|
| `update(User $user, ClubCategory $category)` | `$category->club->owner_uuid === $user->uuid` |
| `delete(User $user, ClubCategory $category)` | `$category->club->owner_uuid === $user->uuid` |

### ClubChannelPolicy
> Protege que solo el owner del club edite/elimine canales.
| Método | Regla |
|---|---|
| `update(User $user, ClubChannel $channel)` | `$channel->category->club->owner_uuid === $user->uuid` |
| `delete(User $user, ClubChannel $channel)` | `$channel->category->club->owner_uuid === $user->uuid` |

### ClubMemberPolicy
> Protege que solo el owner del club expulse miembros.
| Método | Regla |
|---|---|
| `delete(User $user, Club $club)` | `$club->owner_uuid === $user->uuid` |

> **Nota:** Recibe `Club` como segundo parámetro, no `ClubMember`. El controller pasa `[ClubMember::class, $club]`.

### ClubMemberRolePolicy
> Protege que solo el owner del club asigne roles.
| Método | Regla |
|---|---|
| `create(User $user, Club $club)` | `$club->owner_uuid === $user->uuid` |

> **Nota:** Recibe `Club` como segundo parámetro, no `ClubMemberRole`. El controller pasa `[ClubMemberRole::class, $club]`.

### NotificationPolicy
> Protege que solo el dueño marque su notificación como leída.
| Método | Regla |
|---|---|
| `update(User $user, Notification $notification)` | `$notification->user_uuid === $user->uuid` |
| `viewAny(User $user)` | `true` (listado ya filtra por auth) |

### DmConversationPolicy
> Protege que solo los participantes accedan a la DM.
| Método | Regla |
|---|---|
| `view(User $user, DmConversation $dm)` | `$dm->user_one_uuid === $user->uuid \|\| $dm->user_two_uuid === $user->uuid` |
| `create(User $user, DmConversation $dm)` | `$dm->user_one_uuid === $user->uuid \|\| $dm->user_two_uuid === $user->uuid` |
| `viewAny(User $user)` | `true` (listado ya filtra por auth) |

---

## [ ] 3. Registrar las Policies en `AppServiceProvider::boot()`

```php
use App\Models\ChannelMessage;
use App\Models\Club;
use App\Models\ClubCategory;
use App\Models\ClubChannel;
use App\Models\ClubMember;
use App\Models\ClubMemberRole;
use App\Models\DmConversation;
use App\Models\Notification;
use App\Policies\ChannelMessagePolicy;
use App\Policies\ClubCategoryPolicy;
use App\Policies\ClubChannelPolicy;
use App\Policies\ClubMemberPolicy;
use App\Policies\ClubMemberRolePolicy;
use App\Policies\ClubPolicy;
use App\Policies\DmConversationPolicy;
use App\Policies\NotificationPolicy;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::policy(Club::class, ClubPolicy::class);
    Gate::policy(ChannelMessage::class, ChannelMessagePolicy::class);
    Gate::policy(ClubCategory::class, ClubCategoryPolicy::class);
    Gate::policy(ClubChannel::class, ClubChannelPolicy::class);
    Gate::policy(ClubMember::class, ClubMemberPolicy::class);
    Gate::policy(ClubMemberRole::class, ClubMemberRolePolicy::class);
    Gate::policy(Notification::class, NotificationPolicy::class);
    Gate::policy(DmConversation::class, DmConversationPolicy::class);
}
```

---

## [ ] 4. REEMPLAZAR verificaciones inline por `Gate::authorize()`

### NotificationController@markAsRead
```diff
- abort_if($notification->user_uuid !== $request->user()->uuid, 403);
+ Gate::authorize('update', $notification);
```

### DmConversationController@show
```diff
- abort_unless(
-     $dmConversation->user_one_uuid === $request->user()->uuid
-     || $dmConversation->user_two_uuid === $request->user()->uuid,
-     403
- );
+ Gate::authorize('view', $dmConversation);
```

---

## [ ] 5. AÑADIR `Gate::authorize()` donde falta

### ClubController@show
```php
Gate::authorize('view', $club);
// antes de cargar relaciones
```

### DmMessageController@index
```php
Gate::authorize('viewAny', $dmConversation);
// antes de la query
```

### DmMessageController@store
```php
Gate::authorize('create', $dmConversation);
// antes de firstOrCreate
```

---

## [ ] 6. Resumen: qué controllers quedan modificados

| Controller | Método(s) | Acción |
|---|---|---|
| `ClubController` | show, update, destroy | +view ya, +update/destroy ya |
| `ChannelMessageController` | index, store | Ya tienen Gate |
| `ClubCategoryController` | update, destroy | Ya tienen Gate (policy faltaba) |
| `ClubChannelController` | update, destroy | Ya tienen Gate (policy faltaba) |
| `ClubRoleController` | update, destroy | Ya tienen Gate (usa ClubPolicy) |
| `ClubMemberController` | destroy | Ya tiene Gate (policy faltaba) |
| `ClubMemberRoleController` | store | Ya tiene Gate (policy faltaba) |
| `NotificationController` | markAsRead | Reemplazar inline por Gate |
| `DmConversationController` | show | Reemplazar inline por Gate |
| `DmMessageController` | index, store | Añadir Gate (nuevo) |

---

## [ ] 7. Verificar rutas públicas vs protegidas

- [ ] `POST /api/auth/login` y `register` → FUERA de `auth:sanctum`
- [ ] Todo lo demás → DENTRO de `auth:sanctum`

---

## [ ] 8. Probar

| Prueba | Esperado |
|---|---|
| Sin token → ruta protegida | `401` |
| Token sin permiso → recurso ajeno | `403` |
| Usuario A edita su club | `200` |
| Usuario B edita club de A | `403` |
| No miembro ve mensajes del canal | `403` |
| No participante ve DM | `403` |
| No dueño elimina categoría/canal | `403` |
| No dueño expulsa miembro | `403` |
| Dueño de notificación la marca leída | `200` |
| Otro usuario marca notificación ajena | `403` |
