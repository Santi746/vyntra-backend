# Estándares de Documentación PHPDoc

## Propósito

Este archivo define **EXACTAMENTE** cómo se documenta el código en este proyecto.
El objetivo es que **cada clase, propiedad y método sea legible al hacer hover**
en el IDE (VS Code, PhpStorm, etc.), sin necesidad de abrir el archivo o leer
la documentación original de Laravel en inglés.

> **Regla de oro:** Si escribes `Auth::user()` y haces hover, debe aparecer
> "Obtiene el usuario autenticado de la solicitud actual. Retorna null si no hay sesión activa."
> en **español**, no tener que ir a la documentación de Laravel.

---

## 1. Idioma

**TODO** el PHPDoc debe estar en **español**, incluyendo:
- Descripciones de clase
- `@param`, `@return`, `@property`, `@method`
- Comentarios `//` inline

No se acepta inglés, ni siquiera en métodos triviales como `register()` o `boot()`.

---

## 2. Tipos de archivos y su documentación

### 2.1 Models (`app/Models/`)

Cada modelo debe tener un bloque PHPDoc **encima de la clase** con:

```
/**
 * Descripción corta de qué representa este modelo.
 *
 * Párrafo opcional con contexto adicional (relaciones clave,
 * comportamiento especial, reglas de negocio).
 *
 * @property string $uuid        // Clave primaria
 * @property string $name         Descripción del campo
 * @property int    $permissions  Bitmask de permisos
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClubMember> $members
 * @property-read int|null $members_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelName newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelName newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelName query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelName whereUuid($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelName whereName($value)
 */
class ModelName extends Model
```

**Reglas:**
- `@property` por cada columna de la migración (incluyendo PK, FKs, timestamps, softdeletes).
- `@property-read` por cada relación definida en el modelo.
- `@method` para los `where{Columna}()` queries básicas + `newModelQuery()`, `newQuery()`, `query()`.
- Si el modelo usa **Sanctum** (`HasApiTokens`), agregar `createToken()`, `currentAccessToken()`, `tokens()`, `tokenCan()` con sus descripciones en español.
- **NO** poner `@property` redundantes si ya existen en `_ide_helper_models.php`.

### 2.2 Controllers (`app/Http/Controllers/`)

Cada controlador debe tener un bloque PHPDoc **encima de la clase**:

```
/**
 * Descripción corta de qué recursos/adminstra este controlador.
 *
 * Contexto adicional de las operaciones que realiza.
 *
 * @package App\Http\Controllers
 *
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request)
 * @method \Illuminate\Http\JsonResponse store(\App\Http\Requests\SomeRequest $request)
 * @method \Illuminate\Http\JsonResponse show(string $uuid)
 * @method \Illuminate\Http\JsonResponse update(\App\Http\Requests\SomeRequest $request, string $uuid)
 * @method \Illuminate\Http\JsonResponse destroy(string $uuid)
 */
class SomeController extends Controller
```

**Reglas:**
- `@package App\Http\Controllers` siempre.
- `@method` por cada método público del controlador, con su Request class específica si usa una.
- Si el controlador está **vacío** (stub), igual poner la descripción de clase para que al hacer hover se sepa qué va ahí.
- Controlador base `Controller.php` igual debe tener su descripción.

### 2.3 Resources (`app/Http/Resources/`)

Cada Resource debe tener:

```
/**
 * Transforma un modelo en una respuesta JSON estándar.
 *
 * @package App\Http\Resources
 *
 * @property-read \App\Models\SomeModel $resource
 *
 * @return array<string, mixed>
 */
class SomeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            // ...
        ];
    }
}
```

**Reglas:**
- `@package App\Http\Resources` siempre.
- `@property-read \App\Models\XXX $resource` para que el IDE sepa qué modelo se está transformando.
- `@return array<string, mixed>` en `toArray()`.

### 2.4 Form Requests (`app/Http/Requests/`)

Cada Request debe tener:

```
/**
 * Describe qué valida este formulario/petición.
 *
 * @package App\Http\Requests\SomeGroup
 *
 * @return array<string, mixed>
 */
class SomeRequest extends FormRequest
{
    public function rules(): array
    {
        return [...];
    }
}
```

**Reglas:**
- `@package` con el namespace del grupo (`App\Http\Requests\Auth`, `App\Http\Requests\Club`, etc.).
- `@return array<string, mixed>` en `rules()`.
- Si el request tiene `authenticate()`, `prepareForValidation()`, `after()`, ponerles `@return void` o `@return array` según corresponda.

### 2.5 Providers, Jobs, Events, etc.

Cada clase debe tener al menos un bloque de descripción:

```
/**
 * Descripción de qué hace esta clase.
 */
class SomeClass
```

- Métodos públicos documentados con `@param` y `@return`.
- Métodos privados solo si la lógica no es obvia.

---

## 3. Archivos especiales de IDE

### 3.1 `_ide_helper.php`

**NO TOCAR.** Es autogenerado por `php artisan ide-helper:generate`.
Cualquier cambio se pierde al regenerar.

### 3.2 `_ide_helper_models.php`

**NO TOCAR.** Es autogenerado por `php artisan ide-helper:models --nowrite`.
Cualquier cambio se pierde al regenerar.

### 3.3 `_ide_helper_es.php`

**Archivo manual, nunca se sobrescribe.**

Contiene documentación en español para todas las fachadas de Laravel
que más se usan en el proyecto:
- `Auth`, `Hash`, `Cache`, `Storage`, `DB`, `Event`, `Broadcast`, `Log`
- `Validator`, `Response`, `Route`, `Request`, `Config`, `Gate`, `Crypt`
- `Mail`, `Queue`, `Bus`, `Pipeline`, `Redis`, `Notification`, `File`
- `Collection`, `Stringable`, `Http` (Cliente HTTP)

Cada fachada está en su namespace `Illuminate\Support\Facades` con
`@method` para todos sus métodos públicos y descripciones en español
que aparecen al hacer hover.

> **IMPORTANTE:** Si agregas un nuevo método de Laravel que no esté
> documentado aquí, agrégalo a `_ide_helper_es.php` con su descripción
> en español para que aparezca al hacer hover.

---

## 4. Reglas de Formato

### 4.1 Descripciones de clase

```
/**
 * Una línea de qué es esto.
 *
 * Párrafo opcional con más contexto.
 */
```

- Primera línea: resumen corto de una oración.
- Segunda línea: blank.
- Tercera línea+: contexto adicional si es necesario.

### 4.2 Tags `@param` y `@return`

```
 * @param string $uuid UUID del club a buscar
 * @return \Illuminate\Http\JsonResponse
```

- Siempre usar el tipo completo (`\App\Models\Club` no `Club`).
- Usar `string` para UUIDs.
- Usar `int` para bitmasks.
- La descripción del `@param` debe ser corta pero informativa.

### 4.3 Tags `@property` en Models

```
 * @property string $uuid UUID único del club (PK)
 * @property string $name Nombre público del club
 * @property \Illuminate\Support\Carbon|null $created_at
```

- Las **PKs** deben decir "(PK)" al final de la descripción.
- Las **FKs** deben decir "(FK)" al final.
- Los timestamps usar el tipo completo `\Illuminate\Support\Carbon|null`.

### 4.4 Tags `@method`

Para queries Eloquent:
```
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelName whereEmail($value)
```

Para métodos reales del controlador:
```
 * @method \Illuminate\Http\JsonResponse index(\Illuminate\Http\Request $request)
```

---

## 5. Lo que NO se debe hacer

1. **NO** poner `@return void` en métodos que retornan algo obvio como `register()`.
2. **NO** documentar métodos privados a menos que tengan lógica compleja.
3. **NO** poner `@package` en models (solo en controllers, resources y requests).
4. **NO** mezclar español e inglés en el mismo bloque.
5. **NO** modificar `_ide_helper.php` ni `_ide_helper_models.php` (se regeneran).
6. **NO** usar emojis en el código o PHPDoc.
7. **NO** poner comentarios redundantes (`$i++ // incrementa i`).

---

## 6. Verificación

Después de documentar, verificar con:

```bash
php -l app/Models/SomeModel.php
php artisan route:list --path=api
```

Y abrir el archivo en VS Code para confirmar que al hacer hover sobre
cualquier método/propiedad aparezca la descripción en español.

---

## 7. Resumen del estado actual (30 mayo 2026)

| Tipo | Archivos | Cobertura |
|------|----------|-----------|
| Models | 12 | 100% (incluye User con Sanctum methods) |
| Controllers | 16 | 100% (4 implementados + 12 stubs) |
| Resources | 11 | 100% |
| Requests | 15 | 100% |
| Providers | 1 | 100% |
| `_ide_helper_es.php` | 1 | 17 fachadas + Collection + Stringable + Http |
| **TOTAL** | **56** | **100%** |
