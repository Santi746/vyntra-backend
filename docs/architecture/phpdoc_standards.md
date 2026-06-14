# Estándares de Documentación PHPDoc

## Propósito

Mantener la experiencia de **hover en IDE** (VS Code, PhpStorm) con información
útil en español, sin añadir ruido que duplique lo que el código ya declara.

> **Regla de oro:** Si el nombre del método + sus type hints + los imports son
> suficientes para entender qué hace → **no lleva docblock.**

---

## 1. Idioma

Descripciones de clase y comentarios de decisión en **español**.
Tags (`@param`, `@return`, `@property`) **sin descripción** si el nombre
de la variable ya es auto-explicativo.

---

## 2. Lo ÚNICO obligatorio: Models (`app/Models/`)

El IDE no puede inferir columnas de BD ni relaciones Eloquent.

```php
/**
 * Representa un club con sus miembros, roles y canales.
 *
 * @property string      $uuid        UUID único (PK)
 * @property string      $name        Nombre público del club
 * @property string|null $description Descripción opcional
 * @property int         $permissions Bitmask de permisos
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Collection<int, ClubMember>  $members
 * @property-read Collection<int, ClubChannel> $channels
 * @property-read int|null $members_count
 */
class Club extends Model
```

**Reglas:**
- `@property` por cada columna de migración (PK, FKs, timestamps, soft-deletes).
- Marcadores `(PK)` y `(FK)` al final de la descripción.
- `@property-read` por cada relación definida en el modelo.
- Usar short names (`Carbon`, `Collection`) — los imports resuelven.
- **NO** poner `@method whereX()` ni `newQuery()` — `ide-helper` los genera.

---

## 3. Class-level PHPDoc (opcional, recomendado)

Para cualquier clase que se beneficie de tener contexto al hacer hover.

### Controllers

```php
/**
 * CRUD de categorías dentro de un club. Filtra canales privados
 * según permiso VIEW_CHANNELS del usuario autenticado.
 */
class ClubCategoryController extends Controller
```

- 1-2 líneas describiendo el **propósito**, no las operaciones.
- **Sin** `@method` — `php artisan ide-helper:generate` los crea.

### Resources, Requests, Providers, Policies, etc.

```php
/**
 * Transforma un ClubMember en respuesta JSON estándar.
 */
class ClubMemberResource extends JsonResource
```

```php
/**
 * Valida los datos al crear un canal dentro de una categoría.
 */
class StoreClubChannelRequest extends FormRequest
```

```php
/**
 * Registra policies del dominio de clubs y carga migraciones modulares.
 */
class AppServiceProvider extends ServiceProvider
```

---

## 4. Excepciones: cuándo SÍ documentar un método

Solo cuando la lógica **no es obvia** desde la firma:

| Situación | Ejemplo |
|---|---|
| Regla de negocio críptica | `// Owner bypass: el creador del club siempre tiene todos los permisos` |
| Efecto secundario no evidente | `// Soft-delete: no se elimina físicamente, se marca deleted_at` |
| Decisión arquitectónica | `// Excepción: este es el único endpoint público sin membresía` |
| Algoritmo complejo | `// Búsqueda binaria sobre bitmask de permisos` |

**Usar `//` inline**, no PHPDoc block. Ejemplo real de Vyntra:

```php
// Excepción arquitectónica: endpoint público sin membresía.
// Solo devuelve datos básicos + flag is_member.
```

---

## 5. Lo que NO se debe hacer (lista negra)

1. **NO** `@param` sin descripción o con descripción obvia (`$user Usuario autenticado`).
2. **NO** `@return` si el type hint de PHP ya lo declara (`: bool`, `: JsonResponse`).
3. **NO** `@package` — el namespace ya dice dónde vive la clase.
4. **NO** `@method` en controllers — `ide-helper:generate` lo hace automático.
5. **NO** describir métodos cuyo nombre ya lo explica (`viewAny`, `destroy`, `rules`).
6. **NO** FQN en tipos (`\App\Models\User`). Usar imports y short names.
7. **NO** docblocks en métodos triviales (getters, setters, boot, register vacíos).
8. **NO** modificar `_ide_helper.php` ni `_ide_helper_models.php`.
9. **NO** emojis.

---

## 6. Formato estándar de un docblock

```php
/**
 * Clase: 1-2 líneas de propósito.
 *
 * Contexto adicional solo si hay algo no obvio (opcional).
 *
 * @property string $uuid UUID único (PK)  ← solo en Models
 * @property ...
 */
class Foo
```

Sin líneas en blanco innecesarias. Sin tags repetitivos.

---

## 7. Resumen visual

| Cosa | Lleva docblock? |
|---|---|
| Modelo con columnas + relaciones | ✅ `@property` y `@property-read` |
| Controlador con propósito no obvio | ✅ 1-2 líneas de clase |
| Policy, Resource, Request, Provider | ✅ 1 línea de clase |
| Método con lógica compleja/arquitectónica | ✅ `// inline comment` |
| `@param` que duplica type hint | ❌ |
| `@return` que duplica type hint | ❌ |
| `@package` en cualquier clase | ❌ |
| `@method` en controllers | ❌ (ide-helper) |
| Método privado trivial | ❌ |
