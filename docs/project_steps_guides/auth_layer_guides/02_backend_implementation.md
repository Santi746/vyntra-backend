# 🔧 Auth Layer Guide — Implementación Backend

> **Versión:** 2.0  
> **Stack definitivo:** Sanctum 4.0 (API tokens) + Socialite (OAuth2) + 2FA TOTP (pragmarx/google2fa + bacon-qr-code)  
> **NO usa:** Fortify, JWT, sesiones para API  
> **NO incluye:** Tests (cubiertos en guía separada)  
> **Framework:** Laravel 13  
> **Frontend:** Next.js 16 (SPA)  
> **Pre-requisito:** Haber leído `01_concepts_and_architecture.md`  
> **Público:** Programadores que van a implementar la capa de autenticación

---

## 📋 Índice

1. [Paso 0: Inventario y Preparación](#paso-0-inventario-y-preparación)
2. [Paso 1: Migración — Socialite + 2FA](#paso-1-migración--socialite--2fa)
3. [Paso 2: Modelo User — Nuevos Campos y Métodos](#paso-2-modelo-user--nuevos-campos-y-métodos)
4. [Paso 3: AuthResource — Respuesta Unificada](#paso-3-authresource--respuesta-unificada)
5. [Paso 4: Refactorizar AuthController — Login con 2FA](#paso-4-refactorizar-authcontroller--login-con-2fa)
6. [Paso 5: SocialiteController — Login con Google/GitHub/Discord](#paso-5-socialitecontroller--login-con-googlegithubdiscord)
7. [Paso 6: Password Reset — ForgotPassword + ResetPassword](#paso-6-password-reset--forgotpassword--resetpassword)
8. [Paso 7: TwoFactorController — 2FA TOTP Completo](#paso-7-twofactorcontroller--2fa-totp-completo)
9. [Paso 8: EmailVerificationController — Verificación de Email](#paso-8-emailverificationcontroller--verificación-de-email)
10. [Paso 9: FormRequests — Validaciones Específicas](#paso-9-formrequests--validaciones-específicas)
11. [Paso 10: Rutas Completas](#paso-10-rutas-completas)

---

## Paso 0: Inventario y Preparación

Antes de escribir código, verifiquemos qué tenemos y qué falta.

### ✅ Lo que ya existe (verificado)

| Archivo | Estado | Notas |
|---------|--------|-------|
| `User.php` | ✅ Listo | `HasApiTokens`, `HasUuids`, `SoftDeletes`, `Notifiable`, `#[Fillable]`, `#[Hidden]` |
| `AuthController.php` | ✅ Listo | `register()`, `login()`, `logout()` — necesita refactor |
| `LoginRequest.php` | ✅ Listo | `prepareForValidation()` lowercase email. La autenticación (`Auth::attempt()`) está en `AuthController::login()` por SRP — el FormRequest solo valida formato |
| `StoreUserRequest.php` | ✅ Listo | Validación completa de registro |
| `UserResource.php` | ✅ Listo | UUIDs casteados, `whenLoaded()`, campos definidos |
| `UserController.php` | ✅ Listo | `me()`, `show()`, `updateProfile()`, `sessions()` |
| `routes/api.php` | ✅ Listo | Estructura pública/protegida, rutas básicas de auth |
| Migración users | ✅ Listo | PK uuid, campos base, timestamps, softDeletes |
| Migración 2FA (Fortify) | ✅ Existe | `2026_07_02_143622_add_two_factor_columns_to_users_table.php` — agrega `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` |
| `config/sanctum.php` | ✅ Listo | Configuración por defecto |
| `AppServiceProvider.php` | ✅ Listo | Policies registradas, migraciones modulares cargadas |

### ❌ Lo que hay que crear

| Archivo | Propósito |
|---------|-----------|
| `database/migrations/02_users/2026_07_04_163740_add_socialite_and_2fa_fields_to_users.php` | Agregar campos de Socialite + `two_factor_enabled` + `two_factor_backup_codes` |
| `database/migrations/02_users/2026_07_05_000001_make_username_first_name_last_name_nullable_in_users.php` | Permitir `null` en `username`, `first_name`, `last_name` (los llena el user tras OAuth) |
| `database/migrations/02_users/2026_07_05_000002_add_unique_constraint_to_username_in_users.php` | `username` UNIQUE global |
| `app/Http/Resources/AuthResource.php` | Respuesta unificada `{user, token, token_type, profile_completed, requires_2fa}` |
| `app/Http/Controllers/Auth/SocialiteController.php` | Login con Google, GitHub, Discord. Tras OAuth, user tiene username=null hasta complete-profile |
| `app/Http/Controllers/Auth/ForgotPasswordController.php` | Enviar email de reseteo |
| `app/Http/Controllers/Auth/ResetPasswordController.php` | Resetear password y autenticar |
| `app/Http/Controllers/Auth/TwoFactorController.php` | CRUD completo de 2FA TOTP |
| `app/Http/Controllers/Auth/EmailVerificationController.php` | Verificar email y reenviar notificación |
| `app/Http/Requests/Auth/ForgotPasswordRequest.php` | Validar email para forgot password |
| `app/Http/Requests/Auth/ResetPasswordRequest.php` | Validar token + password + confirmación |
| `app/Http/Requests/Auth/EnableTwoFactorRequest.php` | Validar password antes de activar 2FA |
| `app/Http/Requests/Auth/ConfirmTwoFactorRequest.php` | Validar código TOTP al confirmar activación |
| `app/Http/Requests/Auth/VerifyTwoFactorRequest.php` | Validar código TOTP o backup code |
| `app/Http/Requests/Auth/DisableTwoFactorRequest.php` | Validar password + código antes de desactivar |
| `app/Http/Requests/Auth/CompleteProfileRequest.php` | Validar username (único, ignorando al user actual) + first_name + last_name tras OAuth |
| `app/Http/Middleware/RequireFullAuth.php` | Rechazar tokens `2fa_pending` en rutas protegidas (anti-bypass 2FA) |

### ❌ Lo que hay que modificar

| Archivo | Cambio |
|---------|--------|
| `AuthController.php` | Refactor: usar `AuthResource`, login detecta 2FA, token abilities. `register()` solo pide username + first_name + last_name + email + password |
| `UserController.php` | Agregar método `completeProfile()` para `PATCH /api/user/complete-profile` |
| `routes/api.php` | Agregar ruta `PATCH /user/complete-profile` y todas las demás nuevas |
| `User.php` | Nuevos campos en `#[Fillable]`, métodos `has2faEnabled()`, `verifyTwoFactorCode()`, `useBackupCode()`, `generateBackupCodes()`, `disableTwoFactor()`. `username`/`first_name`/`last_name` son nullable (temporales tras OAuth) |

### 📦 Paquetes Composer requeridos

```bash
composer require laravel/socialite
composer require pragmarx/google2fa
composer require bacon/bacon-qr-code
```

> [!NOTE]
> `pragmarx/google2fa` implementa TOTP (Time-based One-Time Password).  
> `bacon/bacon-qr-code` genera códigos QR para compartir el secret con Google Authenticator.  
> `laravel/socialite` es el cliente OAuth2 para login con terceros.

---

## Paso 1: Migración — Socialite + 2FA

### ¿Por qué esta migración?

La tabla `users` ya tiene los campos base y una migración previa (de Fortify) agregó `two_factor_secret`, `two_factor_recovery_codes` y `two_factor_confirmed_at`. Necesitamos **agregar**:

- **Campos de Socialite**: `provider`, `provider_id`, `provider_token`, `provider_refresh_token` — para saber con qué red social se autenticó el usuario y poder refrescar el token.
- **`two_factor_enabled`**: booleano que indica si el usuario completó la activación de 2FA. La mera presencia de `two_factor_secret` no significa que 2FA esté activo — el usuario debe confirmar con un primer código TOTP.
- **`two_factor_backup_codes`**: JSON con 10 códigos de respaldo (backup codes). Reemplaza el campo `two_factor_recovery_codes` de Fortify que usaba texto plano.

> [!CAUTION]
> La migración existente `2026_07_02_143622_add_two_factor_columns_to_users_table.php` agregó `two_factor_secret` y `two_factor_recovery_codes`. Nuestra migración **no duplica** esos campos. Solo agregamos `two_factor_enabled` y `two_factor_backup_codes` (que convivirá con `two_factor_recovery_codes` — nosotros usaremos backup_codes, el otro queda como legacy).

### Código completo

**Archivo:** `database/migrations/02_users/2026_07_04_163740_add_socialite_and_2fa_fields_to_users.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // ─── Campos de Socialite ─────────────────────────────
            $table->string('provider')->nullable()->after('is_online');
            $table->string('provider_id')->nullable()->after('provider');
            $table->text('provider_token')->nullable()->after('provider_id');
            $table->text('provider_refresh_token')->nullable()->after('provider_token');

            // ─── Campos de 2FA ───────────────────────────────────
            // two_factor_secret y two_factor_recovery_codes ya existen
            // (agregados por migration 2026_07_02_143622)
            $table->boolean('two_factor_enabled')->default(false)->after('two_factor_confirmed_at');
            $table->json('two_factor_backup_codes')->nullable()->after('two_factor_enabled');

            // ─── Índices ─────────────────────────────────────────
            $table->index(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['provider', 'provider_id']);
            $table->dropColumn([
                'provider',
                'provider_id',
                'provider_token',
                'provider_refresh_token',
                'two_factor_enabled',
                'two_factor_backup_codes',
            ]);
        });
    }
};
```

### Explicación línea por línea

| Línea | Explicación |
|-------|-------------|
| `$table->string('provider')->nullable()` | Nombre del proveedor social: `'google'`, `'github'`, `'discord'`. `nullable` porque los usuarios con email+password no tienen provider. |
| `$table->string('provider_id')->nullable()` | ID del usuario en el proveedor (ej: el `sub` de Google, el `id` numérico de GitHub). |
| `$table->text('provider_token')->nullable()` | Access token del proveedor. Se guarda en `text` porque puede ser largo. Sirve para hacer llamadas a la API del proveedor en nombre del usuario. |
| `$table->text('provider_refresh_token')->nullable()` | Refresh token del proveedor. Algunos providers (Google) lo envían para renovar el access_token cuando expira. |
| `$table->boolean('two_factor_enabled')->default(false)` | Flag que indica si el usuario **completó** la activación de 2FA. Solo se pone `true` cuando el usuario verifica un primer código TOTP exitosamente. |
| `$table->json('two_factor_backup_codes')->nullable()` | Array JSON con 10 códigos de respaldo. Cada código se guarda hasheado (bcrypt) y se consume al usarlo. |
| `$table->index(['provider', 'provider_id'])` | Índice compuesto para búsquedas rápidas por proveedor + ID. Esencial para el callback de Socialite. |

### Patrones aplicados

- ✅ **§6 Migraciones**: subdirectorio `02_users/`, fecha descriptiva, ALTER migration separada, sin `onDelete('cascade')`, índice en columnas consultadas frecuentemente.
- ✅ **§18 YAGNI**: solo agregamos los campos que vamos a usar hoy. `provider_refresh_token` es nullable porque no todos los providers lo envían.

---

## Paso 1.5: Migraciones — username nullable + UNIQUE

### ¿Por qué estas migraciones?

Vyntra adopta el modelo de **Discord moderno**: `username` único global. El usuario siempre elige su propio `username`. Tras OAuth, los campos `username`, `first_name`, `last_name` quedan `null` hasta que el usuario completa su perfil.

### Código completo

**Archivo 1:** `database/migrations/02_users/2026_07_05_000001_make_username_first_name_last_name_nullable_in_users.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->change();
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable(false)->change();
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
        });
    }
};
```

**Archivo 2:** `database/migrations/02_users/2026_07_05_000002_add_unique_constraint_to_username_in_users.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
        });
    }
};
```

### Por qué username es nullable

- **Register normal**: el user escribe su username en el form. Se persiste con valor.
- **OAuth (Google/GitHub/Discord)**: el callback crea el user con `username = null`. El frontend lee `AuthResource.profile_completed = false` y redirige a `/auth/complete-profile` donde el user elige su username.

### Patrones aplicados

- ✅ **§6 Migraciones**: Migraciones separadas, cada una con propósito claro, todas reversibles.
- ✅ **§18 YAGNI**: Sin sobreingeniería.

---

## Paso 2: Modelo User — Nuevos Campos y Métodos

### ¿Qué cambia?

El modelo `User` necesita:

1. **Nuevos campos en `#[Fillable]`**: los campos de Socialite y 2FA deben ser asignables masivamente.
2. **Métodos de ayuda**: `has2faEnabled()`, `verifyTwoFactorCode()`, `useBackupCode()` — encapsulan la lógica de 2FA en el modelo.
3. **Método `casts()`**: agregar `two_factor_backup_codes` como `array` y `two_factor_enabled` como `boolean`.

### Código completo

> [!IMPORTANT]
> Solo se muestran las partes **modificadas**. El resto del archivo permanece igual.

**Archivo:** `app/Models\User.php`

#### Cambio 1: `#[Fillable]` — agregar nuevos campos

```php
#[Fillable([
    'username', 'first_name', 'last_name',
    'email', 'password', 'bio', 'avatar_url', 'banner_url', 'location', 'is_online',
    // --- Socialite ---
    'provider', 'provider_id', 'provider_token', 'provider_refresh_token',
    // --- 2FA TOTP ---
    'two_factor_secret', 'two_factor_enabled', 'two_factor_backup_codes',
])]
```

**¿Por qué `two_factor_secret` está en Fillable?** Porque cuando el servidor genera el secret (en `TwoFactorController@enable`), lo asigna con `$user->update()` o `$user->forceFill()`. Tenerlo en Fillable permite usar `$user->update()` normalmente.

#### Cambio 2: `#[Hidden]` — sin cambios

No escondemos los nuevos campos porque:
- `provider`, `provider_id` — no son secretos
- `provider_token`, `provider_refresh_token` — no se exponen en API Resources
- `two_factor_secret` — no se expone en API Resources
- `two_factor_backup_codes` — no se exponen en API Resources

#### Cambio 3: `casts()` — agregar tipos

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_enabled' => 'boolean',
        'two_factor_backup_codes' => 'array',
    ];
}
```

**¿Por qué `array` para backup_codes?** Porque la columna es `json` en PostgreSQL. El cast `array` convierte automáticamente el JSON de la DB a un array de PHP, y viceversa. Así trabajamos con `$user->two_factor_backup_codes` como si fuera un array.

#### Cambio 4: Nuevos métodos de 2FA

Agregar estos métodos **dentro** de la clase `User`:

```php
/**
 * Verifica si el usuario tiene 2FA activo y confirmado.
 *
 * Retorna true SOLO si:
 *  - two_factor_enabled = true (el usuario confirmó 2FA con un primer código TOTP)
 *  - Tiene un secret guardado (two_factor_secret no es null)
 */
public function has2faEnabled(): bool
{
    return $this->two_factor_enabled && $this->two_factor_secret !== null;
}

/**
 * Verifica un código TOTP contra el secret del usuario.
 *
 * Usa el paquete pragmarx/google2fa con una ventana de ±1 paso
 * (aprox. 90 segundos de tolerancia) para compensar diferencias
 * de reloj entre el servidor y el teléfono del usuario.
 *
 * @param  string  $code  Código de 6 dígitos ingresado por el usuario
 * @return bool
 */
public function verifyTwoFactorCode(string $code): bool
{
    if (! $this->two_factor_secret) {
        return false;
    }

    $google2fa = new \PragmaRX\Google2FA\Google2FA();

    return $google2fa->verifyKey(
        $this->two_factor_secret,
        $code,
        1  // ventana de tolerancia: ±1 paso (30s antes, 30s después)
    );
}

/**
 * Verifica y consume un backup code.
 *
 * Recorre el array de backup codes, compara cada uno con el código
 * ingresado (usando Hash::check porque los códigos se guardan
 * hasheados con bcrypt). Si encuentra coincidencia:
 *  1. Elimina ese código del array
 *  2. Guarda el array actualizado en la DB
 *  3. Retorna true
 *
 * @param  string  $code  Backup code ingresado (formato: XXXXXXXX)
 * @return bool
 */
public function useBackupCode(string $code): bool
{
    $backupCodes = $this->two_factor_backup_codes ?? [];

    foreach ($backupCodes as $index => $hashedCode) {
        if (Hash::check($code, $hashedCode)) {
            // Eliminar el código usado
            unset($backupCodes[$index]);
            $this->two_factor_backup_codes = array_values($backupCodes);
            $this->save();

            return true;
        }
    }

    return false;
}
```

### Explicación de los métodos

#### `has2faEnabled()`

```php
public function has2faEnabled(): bool
{
    return $this->two_factor_enabled && $this->two_factor_secret !== null;
}
```

Retorna `true` solo si **ambas** condiciones se cumplen:
1. El usuario completó la activación (`two_factor_enabled = true`)
2. Tiene un secret guardado (`two_factor_secret !== null`)

¿Por qué dos condiciones? Por seguridad. Si por un bug `two_factor_enabled` queda en `true` pero el secret se borra, no queremos que el sistema pida 2FA a un usuario que no puede generarlo.

#### `verifyTwoFactorCode()`

```php
$google2fa = new \PragmaRX\Google2FA\Google2FA();
return $google2fa->verifyKey($secret, $code, 1);
```

El método `verifyKey()` toma tres parámetros:
1. **El secret** del usuario (guardado en `two_factor_secret`)
2. **El código** que el usuario ingresó (6 dígitos)
3. **La ventana** de tolerancia (±1 paso = 30 segundos antes y 30 después)

**¿Qué es la ventana de tolerancia?** El código TOTP cambia cada 30 segundos. Si el usuario genera un código en el segundo 29 y lo envía en el segundo 31, el código ya expiró. La ventana de ±1 permite verificar el código actual, el anterior y el siguiente, dando ~90 segundos de margen.

#### `useBackupCode()`

```php
foreach ($backupCodes as $index => $hashedCode) {
    if (Hash::check($code, $hashedCode)) {
        unset($backupCodes[$index]);
        $this->two_factor_backup_codes = array_values($backupCodes);
        $this->save();
        return true;
    }
}
```

**¿Por qué guardar los backup codes hasheados?** Porque son códigos de un solo uso que permiten acceder a la cuenta. Si alguien obtiene la DB, no debe poder leer los backup codes en texto plano. Usamos `Hash::check()` (bcrypt) para comparar, igual que con las contraseñas.

**¿Por qué `array_values()` después de `unset()`?** Porque `unset()` deja un "hueco" en el array (los índices no se reordenan). `array_values()` reindexa el array para que sea secuencial otra vez.

### Patrones aplicados

- ✅ **§5 Models**: `#[Fillable]` attribute, `casts()` method, `HasUuids`, `primaryKey uuid`
- ✅ **§2 SRP**: el modelo encapsula la lógica de verificación 2FA. El controller no necesita saber cómo funciona TOTP internamente — solo llama a `$user->verifyTwoFactorCode($code)`.
- ✅ **§18 YAGNI**: no exponemos `two_factor_secret` ni `two_factor_backup_codes` en ningún Resource. Son datos internos del servidor.

---

## Paso 3: AuthResource — Respuesta Unificada

### ¿Por qué un AuthResource?

Actualmente `AuthController@register` y `AuthController@login` devuelven la respuesta manualmente:

```php
return response()->json([
    'status' => 'success',
    'data' => [
        'user' => new UserResource($user),
        'token' => $token,
    ],
], 201);
```

Esto funciona, pero:
- **No es consistente**: cada desarrollador podría agregar/quitar campos en diferentes endpoints.
- **No soporta 2FA**: necesitamos un campo `requires_2fa` cuando el usuario tiene 2FA activo.
- **No incluye `token_type`**: el frontend necesita saber que el token es de tipo `Bearer`.

`AuthResource` unifica TODAS las respuestas de autenticación: register, login, socialite callback, password reset, verify 2FA.

### Código completo

**Archivo:** `app/Http/Resources/AuthResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource unificado para TODAS las respuestas de autenticación.
 *
 * Contiene:
 *  - user:              El usuario autenticado (UserResource)
 *  - token:             El token Sanctum en texto plano
 *  - token_type:        Siempre 'Bearer'
 *  - profile_completed: true si username NO es null. false = el frontend
 *                       debe redirigir a /auth/complete-profile (caso OAuth)
 *  - requires_2fa:      (opcional) true si el usuario debe completar 2FA
 *
 * @property-read \App\Models\User $resource
 */
class AuthResource extends JsonResource
{
    private string $token;
    private bool $requires2fa;

    /**
     * @param  \App\Models\User  $resource  El usuario autenticado
     * @param  string  $token  Token Sanctum en texto plano
     * @param  bool  $requires2fa  Si el token requiere verificación 2FA
     */
    public function __construct($resource, string $token, bool $requires2fa = false)
    {
        parent::__construct($resource);
        $this->token = $token;
        $this->requires2fa = $requires2fa;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'user' => new UserResource($this->resource),
            'token' => $this->token,
            'token_type' => 'Bearer',
            // El frontend redirige a /auth/complete-profile si esto es false
            'profile_completed' => $this->resource->username !== null,
        ];

        // Solo incluimos 'requires_2fa' si es true (YAGNI)
        if ($this->requires2fa) {
            $data['requires_2fa'] = true;
        }

        return $data;
    }
}
```

### Explicación

#### Constructor

```php
public function __construct($resource, string $token, bool $requires2fa = false)
```

- **`$resource`**: El modelo User (o cualquier instancia). `JsonResource` lo guarda como `$this->resource`.
- **`$token`**: El token Sanctum en texto plano.
- **`$requires2fa`**: `false` por defecto (YAGNI — solo lo enviamos cuando es necesario).

#### toArray()

```php
'requires_2fa' => $this->requires2fa,
```

**¿Por qué `if` en lugar de siempre incluirlo?** Por el principio **§18 YAGNI**: si `requires_2fa` es `false`, no tiene sentido enviarlo. El frontend ya sabe que si no recibe el campo, no necesita 2FA. Esto ahorra ancho de banda y simplifica el contrato.

### Cómo se usa en los controllers

```php
// Register normal (profile_completed: true)
return response()->json([
    'status' => 'success',
    'data' => new AuthResource($user, $token),
], 201);

// Login sin 2FA (profile_completed: true)
return response()->json([
    'status' => 'success',
    'data' => new AuthResource($user, $token),
], 200);

// Login CON 2FA (profile_completed: true)
return response()->json([
    'status' => 'success',
    'data' => new AuthResource($user, $pendingToken, requires2fa: true),
], 200);

// OAuth login (profile_completed: false porque username=null)
return response()->json([
    'status' => 'success',
    'data' => new AuthResource($user, $token),
    // Frontend lee profile_completed: false y redirige a /auth/complete-profile
], 200);
```

> El frontend usa `profile_completed` para saber si debe mostrar `/auth/complete-profile` o redirigir al dashboard.

### Patrones aplicados

- ✅ **§9 Resources**: Resource retorna array plano, casting explícito (UserResource ya castea), `whenLoaded()` para relaciones opcionales, no inventar campos.
- ✅ **§18 YAGNI**: `requires_2fa` solo se incluye cuando es `true`. El contrato es minimalista.

---

## Paso 4: Refactorizar AuthController — Login con 2FA

### ¿Qué cambia?

El `AuthController` actual funciona pero necesita tres cambios importantes:

1. **Usar `AuthResource`** en lugar de arrays manuales — consistencia con el resto del sistema.
2. **Login con detección de 2FA** — si el usuario tiene 2FA activo, en lugar de emitir un token `['*']`, emite un token con ability `['2fa_pending']` y marca `requires_2fa: true`.
3. **Mover `authenticate()` al controller** — el FormRequest SOLO valida (SRP). La autenticación (verificar credenciales) es responsabilidad del controller.

### Código completo

**Archivo:** `app/Http/Controllers/AuthController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\StoreUserRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Controlador de autenticación de Vyntra.
 *
 * Maneja register, login (con/sin 2FA) y logout.
 * NO usa Fortify.
 */
class AuthController extends Controller
{
    /**
     * Registra un nuevo usuario.
     *
     * 1. StoreUserRequest valida los datos (username único, first_name, last_name, email, password)
     * 2. Se crea el usuario — el cast 'hashed' del modelo hashea el password
     * 3. Se emite un token Sanctum con ability ['*'] (completo)
     * 4. Se responde con AuthResource + status 201
     *
     * AuthResource.profile_completed = true (el user pasó username en el form).
     */
    public function register(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'username' => $validated['username'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        // Token completo — el usuario registrado no tiene 2FA
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 201);
    }

    /**
     * Inicia sesión con email y contraseña.
     *
     * 1. LoginRequest valida email + password (formato)
     * 2. Controller autentica con Auth::attempt()
     * 3. Si el usuario tiene 2FA:
     *      - Emite token con ability ['2fa_pending']
     *      - Responde con requires_2fa: true
     * 4. Si NO tiene 2FA:
     *      - Emite token con ability ['*']
     *      - Responde normal
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Autenticar credenciales (movido del FormRequest al controller por SRP)
        if (! Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['Credenciales inválidas.'],
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        // ─── Detección de 2FA ─────────────────────────────────
        if ($user->has2faEnabled()) {
            // Token limitado — solo puede acceder a /auth/2fa/verify
            $pendingToken = $user->createToken('2fa_pending', ['2fa_pending'])->plainTextToken;

            return response()->json([
                'status' => 'success',
                'data' => new AuthResource($user, $pendingToken, requires2fa: true),
            ], 200);
        }

        // Token completo — el usuario no tiene 2FA
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 200);
    }

    /**
     * Cierra la sesión del usuario.
     *
     * Revoca el token actual (lo borra de personal_access_tokens).
     * El frontend debe eliminar el token de su store después
     * de recibir la respuesta exitosa.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'data' => null,
        ], 200);
    }
}
```

### Flujo de login con 2FA (diagrama)

```
Frontend                          Backend
───────                          ──────
1. POST /api/auth/login
   { email, password }
────────────────────────────────►
                                  2. LoginRequest::validate()
                                  3. Auth::attempt()
                                  4. ¿has2faEnabled()?
                                       ├─ No → token ['*'] + requires_2fa=false
                                       └─ Sí → token ['2fa_pending'] + requires_2fa=true
                                  ◄────────────────────────────────
5. ¿requires_2fa?
   ├─ No → guardar token, ir al dashboard
   └─ Sí → guardar token "pendiente",
            mostrar pantalla de código 2FA

6. POST /api/auth/2fa/verify
   { code: 123456 }
   Authorization: Bearer token_pendiente
────────────────────────────────►
                                  7. Verificar código TOTP
                                  8. Revocar token pendiente
                                  9. Emitir token ['*']
                                  ◄────────────────────────────────
10. Guardar token nuevo, ir al dashboard
```

### ¿Por qué abilities y no sesiones?

Discord usa el mismo patrón. Cuando tienes 2FA activo, al hacer login obtienes un `mfa_token` limitado. Ese token solo sirve para verificar el código 2FA. Después obtienes el token real.

**Ventajas:**
- **Stateless**: no necesitas guardar "este usuario está en mitad del 2FA" en Redis.
- **Escalable**: cualquier servidor puede manejar cualquier estado de la autenticación.
- **Consistente**: el frontend trata con tokens en todo momento.

### Patrones aplicados

- ✅ **§2 SRP (Controllers)**: FormRequest valida, Controller orquesta, Resource formatea. Sin `$request->validate()` inline. Sin lógica de negocio en el controller.
- ✅ **§1 Idempotencia**: `register()` usa creación directa (sin `firstOrCreate` porque no hay `client_uuid` en auth — es intencional, el registro es una operación única que no se repite desde el frontend).
- ✅ **§10 Octane**: sin `broadcast()` síncrono, sin propiedades estáticas mutables.
- ✅ **§18 YAGNI**: solo se envía `requires_2fa` cuando es necesario.

---

## Paso 5: SocialiteController — Login con Google/GitHub/Discord

### ¿Qué es Socialite?

Socialite es un **cliente OAuth2** que abstrae toda la complejidad de autenticarse con terceros. Sin Socialite, tendrías que implementar manualmente:

1. Construir la URL de redirect con los parámetros correctos (`client_id`, `redirect_uri`, `scope`, `state`, etc.)
2. Manejar el callback y extraer el `code` de la URL
3. Hacer una petición HTTP al proveedor para intercambiar el `code` por un `access_token`
4. Hacer otra petición HTTP con el `access_token` para obtener los datos del usuario

Con Socialite, todo eso se reduce a dos llamadas:

```php
// 1. Redirigir al usuario al proveedor
return Socialite::driver('google')->redirect();

// 2. Obtener los datos del usuario después del callback
$socialUser = Socialite::driver('google')->user();
```

### Código completo

**Archivo:** `app/Http/Controllers/Auth/SocialiteController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Controlador de autenticación social (OAuth2).
 *
 * Providers soportados: google, github, discord.
 * El flujo es:
 *   1. redirect()  → redirige al usuario al proveedor
 *   2. callback()  → el proveedor redirige de vuelta con un code
 *   3. Se intercambia el code por datos del usuario
 *   4. Se busca o crea el usuario en la DB
 *   5. Se emite un token Sanctum
 *   6. Se redirige al frontend con el token en la URL
 */
class SocialiteController extends Controller
{
    /**
     * Redirige al usuario al proveedor social.
     *
     * Socialite construye automáticamente la URL de OAuth2
     * con los parámetros correctos (client_id, redirect_uri, scope, state).
     * Laravel responde con un HTTP 302 (redirect).
     */
    public function redirect(string $provider)
    {
        // El provider se valida en la ruta con ->where('provider', '...')
        // (Paso 10), por lo que no repetimos la validación acá.
        return Socialite::driver($provider)->redirect();
    }

    /**
     * Maneja el callback del proveedor social.
     *
     * 1. Socialite intercambia el code por un access_token
     * 2. Busca un usuario existente por provider_id o email
     * 3. Si existe: actualiza datos (avatar, token)
     * 4. Si no existe: crea usuario nuevo con datos del proveedor
     * 5. Genera token Sanctum
     * 6. Redirige al frontend con el token
     */
    public function callback(string $provider, Request $request): RedirectResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Laravel\Socialite\Two\InvalidStateException $e) {
            // OAuth state inválido o expirado (CSRF protection)
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/auth/callback?error=invalid_state');
        }

        $user = $this->findOrCreateSocialUser($provider, $socialUser);

        // Si el usuario tiene 2FA, emitir token pendiente (no bypass)
        if ($user->has2faEnabled()) {
            $pendingToken = $user->createToken('2fa_pending', ['2fa_pending'])->plainTextToken;
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            return redirect($frontendUrl . '/auth/callback?token=' . $pendingToken . '&requires_2fa=1');
        }

        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

        return redirect($frontendUrl . '/auth/callback?token=' . $token);
    }

    /**
     * Busca o crea un usuario a partir de los datos del proveedor social.
     *
     * Orden de búsqueda:
     *   1. provider + provider_id (usuario ya vinculado)
     *   2. email (vincular cuenta existente)
     *   3. Crear nuevo usuario — username/first_name/last_name = null.
     *      El frontend redirige a /auth/complete-profile para que el user
     *      complete esos 3 campos.
     *
     * @param  string  $provider  'google', 'github', 'discord'
     * @param  \Laravel\Socialite\Two\User  $socialUser  Datos del proveedor
     */
    private function findOrCreateSocialUser(string $provider, $socialUser): User
    {
        // ─── 1. Buscar por provider_id ──────────────────────────
        $user = User::where('provider', $provider)
            ->where('provider_id', (string) $socialUser->id)
            ->first();

        if ($user) {
            $user->update(['provider_token' => $socialUser->token]);
            return $user;
        }

        // ─── 2. Buscar por email (vincular cuenta existente) ─────────
        $user = User::where('email', $socialUser->email)->first();

        if ($user) {
            $user->update([
                'provider' => $provider,
                'provider_id' => (string) $socialUser->id,
                'provider_token' => $socialUser->token,
                'avatar_url' => $socialUser->avatar ?? $user->avatar_url,
            ]);
            return $user;
        }

        // ─── 3. Crear usuario nuevo ─────────────────────────────────
        // username, first_name, last_name = null. El frontend debe redirigir
        // a /auth/complete-profile para que el user los complete.
        return User::create([
            'username' => null,
            'first_name' => null,
            'last_name' => null,
            'email' => $socialUser->email,
            'password' => Str::random(32), // el user no conoce esta pass. cast 'hashed' del modelo la hashea
            'provider' => $provider,
            'provider_id' => (string) $socialUser->id,
            'provider_token' => $socialUser->token,
            'avatar_url' => $socialUser->avatar,
            'email_verified_at' => now(), // OAuth = email verificado por el provider
        ]);
    }
}
```

### Explicación del flujo Socialite

```
FRONTEND (Next.js)              BACKEND (Laravel)           GOOGLE
      │                              │                        │
      │ 1. Click "Continuar con Google"
      │ redirect to:
      │ /api/auth/social/google
      │─────────────────────────────►│
      │                              │ 2. Socialite::driver('google')
      │                              │    ->redirect()
      │                              │    (construye URL de Google)
      │ 3. HTTP 302 redirect a:       │
      │◄─────────────────────────────│
      │    accounts.google.com        │
      │    ?client_id=xxx&scope=email │
      │                              │
      │ 4. Navegador redirige         │
      │────────────────────────────────────────────►│
      │                              │               │
      │                              │               5. Usuario inicia
      │                              │                  sesión en Google
      │                              │               6. Usuario acepta
      │                              │                  permisos
      │                              │               │
      │ 7. Google redirect a:         │               │
      │    /api/auth/social/google/   │               │
      │    callback?code=xxx          │               │
      │◄─────────────────────────────────────────────│
      │                              │
      │ 8. Navegador sigue redirect   │
      │─────────────────────────────►│
      │                              │ 9. Socialite::driver('google')
      │                              │    ->user()
      │                              │    (intercambia code por
      │                              │     access_token + datos)
      │                              │
      │                              │ 10. Buscar/crear usuario
      │                              │ 11. createToken()
      │                              │
      │ 12. HTTP 302 redirect a:      │
      │    /auth/callback?token=xxx   │
      │◄─────────────────────────────│
      │                              │
      │ 13. Frontend lee token de URL
      │ 14. Guarda en store
      │ 15. Redirige a dashboard
```

### ¿Por qué redirect y no JSON en el callback?

Cuando Google redirige al navegador del usuario a nuestro callback, el navegador está siguiendo una **redirección HTTP**. No hay código JavaScript que pueda leer un JSON. Por eso el callback:

1. Procesa el código de Google
2. Genera el token Sanctum
3. **Redirige al frontend** con el token en la URL: `redirect(FRONTEND_URL . '/auth/callback?token=' . $token)`

El frontend debe tener una página `/auth/callback` que:
1. Lea el token de `window.location.search`
2. Lo guarde en el Zustand store
3. Redirija al dashboard

### Patrones aplicados

- ✅ **§2 SRP**: Controller solo orquesta, no valida ni formatea.
- ✅ **§5 Models**: `User::create()` con `#[Fillable]` controlado.
- ✅ **§7 FormRequests**: No aplica — Socialite no usa FormRequest porque los datos vienen del proveedor, no del usuario.
- ✅ **§9 Resources**: `AuthResource` formatea la respuesta (aunque en este caso respondemos con redirect).
- ✅ **§18 YAGNI**: Solo guardamos los campos que necesitamos del proveedor social.

---

## Paso 6: Password Reset — ForgotPassword + ResetPassword

### ¿Qué es Password Reset?

Laravel incluye un sistema de reseteo de contraseñas basado en:
1. **Tabla `password_reset_tokens`** — guarda un token asociado al email, con timestamp de creación.
2. **`Password` facade** — `Password::sendResetLink()` envía el email, `Password::reset()` verifica el token y ejecuta el callback de actualización.

Nosotros envolvemos este sistema en dos controllers personalizados (sin Fortify).

### 6.1 ForgotPasswordController

**Archivo:** `app/Http/Controllers/Auth/ForgotPasswordController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;

/**
 * Envía un email con el enlace de restablecimiento de contraseña.
 *
 * Por seguridad, SIEMPRE responde con success 200,
 * independientemente de si el email existe o no.
 * Esto evita que un atacante pueda enumerar emails registrados.
 */
class ForgotPasswordController extends Controller
{
    /**
     * __invoke permite usar este controlador como invocable
     * en las rutas (single-action controller).
     */
    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Laravel internamente:
        // 1. Busca el email en users
        // 2. Genera un token aleatorio
        // 3. Lo guarda en password_reset_tokens
        // 4. Envía un email con el token (usando el template de notificaciones)
        $status = Password::sendResetLink($validated);

        // Respondemos success siempre (seguridad por oscuridad)
        return response()->json([
            'status' => 'success',
            'message' => __('Si el correo existe, recibirás un enlace de restablecimiento.'),
        ], 200);
    }
}
```

### 6.2 ResetPasswordController

**Archivo:** `app/Http/Controllers/Auth/ResetPasswordController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\AuthResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Restablece la contraseña del usuario usando el token
 * recibido por email y AUTENTICA al usuario automáticamente.
 *
 * Esto significa que el usuario no necesita hacer login después
 * de resetear su contraseña — ya recibe un token Sanctum.
 */
class ResetPasswordController extends Controller
{
    public function __invoke(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Password::reset() verifica:
        // 1. Que el token existe y no ha expirado
        // 2. Que el email coincide
        // 3. Ejecuta el callback con los datos validados
        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        // Si el token es inválido o expiró
        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'error',
                'message' => __($status), // Laravel traduce: "passwords.token", "passwords.expired", etc.
            ], 400);
        }

        // Autenticar al usuario automáticamente
        // Buscamos al usuario por email (ya validado por Password::reset)
        $user = User::where('email', $validated['email'])->firstOrFail();
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 200);
    }
}
```

### Explicación del flujo de Password Reset

```
Frontend                          Backend                        Email
───────                          ──────                         ─────
1. { email }
   POST /api/auth/forgot-password
────────────────────────────────►
                                  2. Password::sendResetLink()
                                  3. Busca user por email
                                  4. Genera token aleatorio
                                  5. Guarda en password_reset_tokens
                                  6. Envía email
                                  ◄───── "success" (siempre) ────
                                                                   7. User recibe email
                                                                      con enlace:
                                                   ──── enlace ───►
                                                                   frontend/reset-password?
                                                                   token=xxx&email=yyy
8. User ve formulario
   de nueva contraseña

9. { email, token,
     password, password_confirmation }
   POST /api/auth/reset-password
────────────────────────────────►
                                  10. Password::reset()
                                  11. Verifica token + email
                                  12. Actualiza password
                                  13. Elimina token de password_reset_tokens
                                  14. createToken('auth_token')
                                  ◄────────────────────────────────
                                  { user, token }
15. Usuario autenticado
    sin necesidad de login
```

### ¿Por qué autenticar automáticamente después del reset?

**Experiencia de usuario (UX):** Si el usuario acaba de resetear su contraseña, lo último que quiere hacer es volver a escribir email + contraseña en un formulario de login. Es más fluido que el reset **también** inicie sesión.

**Seguridad:** El token de reseteo es una prueba suficiente de que el usuario controla el email. Si un atacante tuviera acceso al email, podría tanto resetear la contraseña como interceptar el token post-reset. No hay ganancia de seguridad en pedir login después del reset.

### Patrones aplicados

- ✅ **§2 SRP (Controllers)**: ForgotPasswordRequest valida, ForgotPasswordController solo llama a `Password::sendResetLink()`.
- ✅ **§7 FormRequests**: `authorize() = true`, `rules()` con array syntax, `prepareForValidation()` lowercase email, `exists:users,email`.
- ✅ **§9 Resources**: `AuthResource` devuelve el usuario + token después del reset.
- ✅ **§18 YAGNI**: No exponemos el status real de `Password::sendResetLink()` para prevenir email enumeration.

---

## Paso 7: TwoFactorController — 2FA TOTP Completo

### ¿Qué hace este controlador?

Maneja el ciclo de vida completo de 2FA:

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| `enable()` | POST `/api/auth/2fa/enable` | Inicia activación: verifica password, genera secret + QR |
| `confirm()` | POST `/api/auth/2fa/confirm` | Completa activación: verifica primer código TOTP, genera backup codes |
| `verify()` | POST `/api/auth/2fa/verify` | Verifica código TOTP durante login (token pendiente → token completo) |
| `disable()` | POST `/api/auth/2fa/disable` | Desactiva 2FA: verifica password + código TOTP |

### Código completo

**Archivo:** `app/Http/Controllers/Auth/TwoFactorController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmTwoFactorRequest;
use App\Http\Requests\Auth\DisableTwoFactorRequest;
use App\Http\Requests\Auth\EnableTwoFactorRequest;
use App\Http\Requests\Auth\VerifyTwoFactorRequest;
use App\Http\Resources\AuthResource;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

/**
 * Controlador de autenticación de dos factores (2FA TOTP).
 *
 * Implementa el flujo completo:
 *   1. Enable: generar secret + QR
 *   2. Confirm: verificar primer código + generar backup codes
 *   3. Verify: verificar código durante login (token pendiente)
 *   4. Disable: desactivar 2FA
 *
 * Basado en TOTP (RFC 6238). Compatible con Google Authenticator,
 * Authy, 1Password, y cualquier app que soporte TOTP.
 */
class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * PASO 1: Iniciar activación de 2FA.
     *
     * Requiere: password actual (por seguridad).
     * Genera:
     *  - Un secret TOTP (guardado en two_factor_secret)
     *  - Un QR code (para escanear con Google Authenticator)
     *  - La URL manual (otpauth://) para copiar/pegar
     *
     * NO marca two_factor_enabled = true todavía.
     * El usuario debe CONFIRMAR con un primer código TOTP.
     */
    public function enable(EnableTwoFactorRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Generar secret TOTP y guardarlo (aún no activado)
        $secret = $this->google2fa->generateSecretKey(32);
        $user->two_factor_secret = $secret;
        $user->save();

        $qrData = $this->generateQrCode($secret, $user->email);

        return response()->json([
            'status' => 'success',
            'data' => $qrData,
        ], 200);
    }

    /**
     * Genera el QR code SVG + URL manual para configurar Google Authenticator.
     *
     * @return array{qr_code: string, secret: string, manual_url: string}
     */
    private function generateQrCode(string $secret, string $email): array
    {
        $qrUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString($qrUrl);

        return [
            'qr_code' => 'data:image/svg+xml;base64,' . base64_encode($qrSvg),
            'secret' => $secret,
            'manual_url' => $qrUrl,
        ];
    }

    /**
     * PASO 2: Confirmar activación de 2FA.
     *
     * Requiere: código TOTP de 6 dígitos.
     * Si el código es válido:
     *  1. Marca two_factor_enabled = true
     *  2. Genera 10 backup codes (hasheados con bcrypt)
     *  3. Los guarda en two_factor_backup_codes
     *
     * Devuelve los backup codes en texto plano para que el usuario
     * los guarde. NUNCA se vuelven a mostrar.
     */
    public function confirm(ConfirmTwoFactorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (! $user->verifyTwoFactorCode($validated['code'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código inválido. Asegúrate de haber escaneado el QR correctamente.',
            ], 422);
        }

        // Activar 2FA y generar backup codes en el modelo
        $user->two_factor_enabled = true;
        $plainCodes = $user->generateBackupCodes();
        $user->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => '2FA activado correctamente. Guarda estos códigos de respaldo en un lugar seguro.',
                'backup_codes' => $plainCodes,
            ],
        ], 200);
    }

    /**
     * PASO 3: Verificar 2FA durante el login.
     *
     * Este endpoint se llama DESPUÉS de que el usuario hizo login
     * y recibió un token con ability ['2fa_pending'].
     *
     * Flujo:
     *  1. Recibe el código TOTP (6 dígitos) o backup code (8 caracteres)
     *  2. Si es TOTP: verifica con verifyTwoFactorCode()
     *  3. Si es backup code: verifica con useBackupCode()
     *  4. Revoca el token pendiente
     *  5. Emite un token completo ['*']
     *  6. Devuelve AuthResource
     */
    public function verify(VerifyTwoFactorRequest $request): JsonResponse
    {
        $validated = $request->validated();

        /** @var \App\Models\User $user */
        $user = $request->user();

        $code = $validated['code'];
        $isValid = false;

        // Determinar si es TOTP (6 dígitos) o backup code (8 caracteres)
        if (strlen($code) === 6 && ctype_digit($code)) {
            // Código TOTP
            $isValid = $user->verifyTwoFactorCode($code);
        } else {
            // Backup code
            $isValid = $user->useBackupCode($code);
        }

        if (! $isValid) {
            return response()->json([
                'status' => 'error',
                'message' => 'Código inválido o expirado.',
            ], 422);
        }

        // Revocar el token pendiente (actual)
        $request->user()->currentAccessToken()->delete();

        // Emitir token completo
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => new AuthResource($user, $token),
        ], 200);
    }

    /**
     * PASO 4: Desactivar 2FA.
     *
     * Requiere: password actual + código TOTP (doble verificación).
     * Limpia: two_factor_secret, two_factor_enabled, two_factor_backup_codes.
     */
    public function disable(DisableTwoFactorRequest $request): JsonResponse
    {
        // El FormRequest ya verificó password + código TOTP via after()
        $request->user()->disableTwoFactor();

        return response()->json([
            'status' => 'success',
            'message' => '2FA desactivado correctamente.',
        ], 200);
    }
}
```

### Explicación detallada de cada método

#### `enable()` — Generar secret + QR

```php
$secret = $this->google2fa->generateSecretKey(32);
$qrUrl = $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
```

`generateSecretKey(32)` genera un string aleatorio de 32 caracteres en base32. Ese string es el "secreto compartido" entre el servidor y Google Authenticator.

`getQRCodeUrl()` construye una URL del formato:

```
otpauth://totp/Vyntra:user@email.com?secret=JBSWY3DPEHPK3PXP&issuer=Vyntra
```

Google Authenticator entiende esta URL y la convierte en un QR. Cuando el usuario escanea el QR, su teléfono guarda el secret y empieza a generar códigos cada 30 segundos.

#### `confirm()` — Activar 2FA + generar backup codes

Después de que el usuario escanea el QR, debe ingresar un primer código TOTP para confirmar que todo funciona. Si el código es válido:

1. Se marca `two_factor_enabled = true`
2. Se generan 10 backup codes aleatorios de 8 caracteres
3. Los códigos se guardan **hasheados** con bcrypt
4. Se devuelven en **texto plano** al usuario (solo se muestran una vez)

#### `verify()` — Verificar 2FA durante login

Este es el endpoint que se llama después del login cuando el usuario tiene 2FA activo. Acepta tanto códigos TOTP (6 dígitos) como backup codes (8 caracteres).

```php
if (strlen($code) === 6 && ctype_digit($code)) {
    $isValid = $user->verifyTwoFactorCode($code);
} else {
    $isValid = $user->useBackupCode($code);
}
```

**¿Cómo distinguimos TOTP de backup code?** Por el formato:
- TOTP: 6 dígitos numéricos (ej: `482951`)
- Backup code: 8 caracteres alfanuméricos (ej: `XK7P-M9N2`)

Si es un backup code, se consume (se elimina del array). Los backup codes son de **un solo uso**.

#### `disable()` — Desactivar 2FA

Requiere doble verificación:
1. **Password**: para asegurar que es el dueño de la cuenta
2. **Código TOTP**: para asegurar que tiene acceso al autenticador

Si ambas verificaciones pasan, se limpian todos los campos de 2FA.

### Diagrama de flujo completo de 2FA

```
                    ┌─────────────────────────────────────┐
                    │         ACTIVACIÓN DE 2FA           │
                    └─────────────────────────────────────┘

Usuario                  Frontend                   Backend
───────                  ───────                    ──────
                         1. POST /api/auth/2fa/enable
                            { password: "..." }
                         ───────────────────────────────►
                                                        2. Verificar password
                                                        3. Generar secret TOTP
                                                        4. Generar QR code
                                                        5. Guardar secret (no activado)
                        ◄───────────────────────────────
                          { qr_code, secret, manual_url }
                         
6. Usuario escanea QR
   con Google Authenticator

7. Usuario ingresa
   código de 6 dígitos
                         8. POST /api/auth/2fa/confirm
                            { code: 482951 }
                         ───────────────────────────────►
                                                        9. Verificar código TOTP
                                                        10. Activar 2FA
                                                        11. Generar 10 backup codes
                        ◄───────────────────────────────
                          { backup_codes: [...] }
12. Usuario GUARDA
    los backup codes
    en un lugar seguro


                    ┌─────────────────────────────────────┐
                    │         LOGIN CON 2FA              │
                    └─────────────────────────────────────┘

Usuario                  Frontend                   Backend
───────                  ───────                    ──────
                         1. POST /api/auth/login
                            { email, password }
                         ───────────────────────────────►
                                                        2. Auth::attempt()
                                                        3. has2faEnabled() → true
                                                        4. Token ['2fa_pending']
                        ◄───────────────────────────────
                          { requires_2fa: true,
                            token: "1|abc..." }

5. Usuario ve pantalla
   de código 2FA

6. Usuario ingresa
   código TOTP
                         7. POST /api/auth/2fa/verify
                            { code: 482951 }
                            Authorization: Bearer 1|abc...
                         ───────────────────────────────►
                                                        8. verifyTwoFactorCode()
                                                        9. Revocar token pendiente
                                                        10. Token ['*']
                        ◄───────────────────────────────
                          { user, token: "2|xyz..." }
11. Usuario en dashboard


                    ┌─────────────────────────────────────┐
                    │      LOGIN CON BACKUP CODE         │
                    └─────────────────────────────────────┘

(flujo similar, pero el código es de 8 caracteres
 y se CONSUME al usarlo — solo se puede usar una vez)
```

### Patrones aplicados

- ✅ **§2 SRP**: Controller orquesta, modelo verifica (`verifyTwoFactorCode`, `useBackupCode`), FormRequest valida.
- ✅ **§5 Models**: Métodos helper en el modelo encapsulan lógica de TOTP.
- ✅ **§7 FormRequests**: `authorize() = true`, `after()` para validaciones complejas (verificar password).
- ✅ **§9 Resources**: `AuthResource` para respuesta de `verify()`.
- ✅ **§10 Octane**: Sin propiedades estáticas, sin `broadcast()` síncrono. El `Google2FA` se instancia en el constructor (se puede inyectar via Service Container si se desea).
- ✅ **§18 YAGNI**: Backup codes se guardan hasheados. QR se genera solo cuando se pide. No se guarda el QR en DB.

---

## Paso 8: EmailVerificationController — Verificación de Email

### ¿Qué hace?

Laravel incluye un sistema de verificación de email que:
1. Envía un email con un enlace firmado (verificación).
2. El enlace contiene el UUID del usuario y un hash de su email.
3. Cuando el usuario hace clic, se marca `email_verified_at`.

Nosotros envolvemos esto en un controlador personalizado.

### Código completo

**Archivo:** `app/Http/Controllers/Auth/EmailVerificationController.php`

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Controlador de verificación de email.
 *
 * Implementa dos endpoints:
 *   1. verify($id, $hash) → Verifica el email del usuario
 *   2. notification() → Reenvía el email de verificación
 *
 * Usa las firmas (signed URLs) de Laravel para asegurar
 * que el enlace de verificación no sea falsificable.
 */
class EmailVerificationController extends Controller
{
    /**
     * Verifica el email del usuario.
     *
     * El enlace de verificación contiene:
     *  - {id}: UUID del usuario
     *  - {hash}: sha1 del email del usuario
     *
     * Laravel verifica que el hash coincida con el email actual
     * del usuario, y que la URL esté firmada (no haya sido
     * modificada por un atacante).
     *
     * @param  string  $id  UUID del usuario
     * @param  string  $hash  sha1 del email del usuario
     */
    public function verify(string $id, string $hash, Request $request): JsonResponse
    {
        // Buscar usuario por UUID
        $user = User::findOrFail($id);

        // Verificar que el hash coincide con el email
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enlace de verificación inválido.',
            ], 403);
        }

        // Verificar que la URL está firmada (no expiró ni fue modificada)
        if (! URL::hasValidSignature($request)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enlace de verificación expirado o inválido.',
            ], 403);
        }

        // Marcar email como verificado (si no lo estaba ya)
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'message' => 'El email ya estaba verificado.',
            ], 200);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'status' => 'success',
            'message' => 'Email verificado correctamente.',
        ], 200);
    }

    /**
     * Reenvía el email de verificación.
     *
     * Rate limited a 1 vez por minuto (throttle:10,1 en rutas).
     * Si el email ya está verificado, responde error.
     */
    public function notification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El email ya está verificado.',
            ], 400);
        }

        // Laravel envía el email usando el Notifiable trait
        $user->sendEmailVerificationNotification();

        return response()->json([
            'status' => 'success',
            'message' => 'Email de verificación reenviado.',
        ], 200);
    }
}
```

### Explicación

#### `verify($id, $hash)`

El enlace de verificación se ve así:

```
https://api.vyntra.com/api/auth/email/verify/{id}/{hash}?signature=xxx&expires=yyy
```

- **`{id}`**: UUID del usuario (para saber a quién verificar)
- **`{hash}`**: `sha1(email)` — para asegurar que el email no cambió
- **`signature`**: Firma HMAC de Laravel — para asegurar que la URL no fue modificada
- **`expires`**: Timestamp de expiración (típicamente 1 hora)

Laravel verifica automáticamente la firma con `URL::hasValidSignature()`.

#### `notification()`

Reenvía el email de verificación. Está protegido por rate limiting (`throttle:10,1` en rutas) para evitar spam.

### Patrones aplicados

- ✅ **§2 SRP**: Controller solo orquesta. El modelo `User` (via `MustVerifyEmail` trait) maneja el envío de notificaciones.
- ✅ **§18 YAGNI**: Respuestas minimalistas. No exponemos información del usuario en los mensajes de error.

---

## Paso 9: FormRequests — Validaciones Específicas

### 9.1 ForgotPasswordRequest

**Archivo:** `app/Http/Requests/Auth/ForgotPasswordRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la solicitud de restablecimiento de contraseña.
 *
 * Solo requiere el email. No autentica al usuario.
 */
class ForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el email a minúsculas antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
        ];
    }
}
```

### 9.2 ResetPasswordRequest

**Archivo:** `app/Http/Requests/Auth/ResetPasswordRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida el restablecimiento de contraseña.
 *
 * Requiere:
 *  - email: para identificar al usuario
 *  - token: el token recibido por email
 *  - password: la nueva contraseña (con confirmación)
 */
class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza el email a minúsculas antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower($this->email),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
```

### 9.3 EnableTwoFactorRequest

**Archivo:** `app/Http/Requests/Auth/EnableTwoFactorRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Valida la solicitud de activación de 2FA.
 *
 * Requiere la contraseña actual como medida de seguridad.
 * Usa after() para verificar la contraseña contra el hash en DB.
 */
class EnableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Verifica que la contraseña actual sea correcta.
     * Esto se ejecuta DESPUÉS de que las reglas pasen.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $user = $this->user();

                if (! $user || ! Hash::check($this->password, $user->password)) {
                    $validator->errors()->add(
                        'password',
                        'La contraseña actual es incorrecta.'
                    );
                }
            },
        ];
    }
}
```

**¿Por qué `after()` y no en el controller?** Por SRP. La validación (incluyendo la verificación de la contraseña actual) es responsabilidad del FormRequest. El controller no debería tener lógica de validación.

### 9.4 ConfirmTwoFactorRequest

**Archivo:** `app/Http/Requests/Auth/ConfirmTwoFactorRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida la confirmación de activación de 2FA.
 *
 * Requiere el código TOTP de 6 dígitos que el usuario
 * obtiene al escanear el QR generado por enable().
 */
class ConfirmTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
```

### 9.5 VerifyTwoFactorRequest

**Archivo:** `app/Http/Requests/Auth/VerifyTwoFactorRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida el código 2FA durante el login.
 *
 * Acepta:
 *  - Código TOTP: exactamente 6 dígitos numéricos
 *  - Backup code: exactamente 8 caracteres alfanuméricos
 *
 * La validación lógica (si el código es correcto) se hace en el controller.
 */
class VerifyTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
```

**¿Por qué no validar el formato exacto aquí?** Porque el código puede ser TOTP (6 dígitos) o backup code (8 caracteres). La distinción se hace en el controller. Validar solo `string` es suficiente — la verificación criptográfica ocurre después.

### 9.6 DisableTwoFactorRequest

**Archivo:** `app/Http/Requests/Auth/DisableTwoFactorRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Valida la solicitud de desactivación de 2FA.
 *
 * Requiere DOBLE verificación:
 *  1. Contraseña actual (quién eres)
 *  2. Código TOTP (tienes acceso al autenticador)
 *
 * Ambas se verifican en after().
 */
class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'code' => ['required', 'string'],
        ];
    }

    /**
     * Verifica password + código TOTP después de las reglas básicas.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $user = $this->user();

                // 1. Verificar contraseña
                if (! $user || ! Hash::check($this->password, $user->password)) {
                    $validator->errors()->add(
                        'password',
                        'La contraseña actual es incorrecta.'
                    );
                    return;
                }

                // 2. Verificar código TOTP (usar el método del modelo — DRY)
                if (! $user->verifyTwoFactorCode($this->code)) {
                    $validator->errors()->add(
                        'code',
                        'El código TOTP es inválido.'
                    );
                }
            },
        ];
    }
}
```

### 9.7 CompleteProfileRequest

**Archivo:** `app/Http/Requests/Auth/CompleteProfileRequest.php`

```php
<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida la finalización del perfil tras OAuth.
 *
 * El user llega del callback de Google/GitHub/Discord con
 * username/first_name/last_name null. Este endpoint recibe los
 * 3 datos de oro y los persiste.
 */
class CompleteProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normaliza username a minúsculas antes de validar.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('username')) {
            $this->merge([
                'username' => strtolower($this->username),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:50',
                // El user actual no cuenta para la validación unique
                // (por si reenvía el mismo username)
                Rule::unique('users', 'username')->ignore($this->user()->uuid, 'uuid'),
            ],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
        ];
    }
}
```

**Endpoint asociado:** `PATCH /api/user/complete-profile` en `UserController`:

```php
public function completeProfile(CompleteProfileRequest $request): JsonResponse
{
    $validated = $request->validated();

    $request->user()->update($validated);

    return response()->json([
        'status' => 'success',
        'data' => new UserResource($request->user()->fresh()),
    ], 200);
}
```

**¿Por qué un FormRequest separado y no parte de `UpdateUserRequest`?** Porque son casos de uso distintos:
- `UpdateUserRequest` valida campos opcionales de perfil (bio, avatar, banner, location)
- `CompleteProfileRequest` valida username único (ignorando al user actual) + first_name + last_name
- Mezclar ambos inflaría el request con lógica condicional y reglas divergentes

### Resumen de FormRequests

| FormRequest | Campos | Validación especial |
|-------------|--------|---------------------|
| `ForgotPasswordRequest` | email | `prepareForValidation()` lowercase |
| `ResetPasswordRequest` | email, token, password, password_confirmation | `prepareForValidation()` lowercase, `exists:users,email` |
| `EnableTwoFactorRequest` | password | `after()` verifica password contra hash |
| `ConfirmTwoFactorRequest` | code | Solo string — la verificación TOTP es en controller |
| `VerifyTwoFactorRequest` | code | Solo string — la verificación lógica es en controller |
| `DisableTwoFactorRequest` | password, code | `after()` verifica password + TOTP (usa `verifyTwoFactorCode()`) |
| `CompleteProfileRequest` | username, first_name, last_name | `username` unique ignorando al user actual, `prepareForValidation()` lowercase |

### Patrones aplicados

- ✅ **§7 FormRequests**: `authorize() = true`, `rules()` con array syntax, `prepareForValidation()`, `exists:table,column`, `after()` para validación post-hook con DB.
- ✅ **§2 SRP**: La validación está en los FormRequests, no en los controllers.

---

## Paso 9.5: Middleware RequireFullAuth — Protección contra bypass de 2FA

### ¿Por qué este middleware? (Histórico — actualmente eliminado)

> ⚠️ **Actualización:** Este middleware fue **eliminado** del backend. La protección contra bypass de 2FA se logra directamente con los abilities de Sanctum en las rutas:
> - El grupo de rutas protegidas usa `auth:sanctum` + `ability:*` → un token con ability `2fa_pending` (que NO tiene `*`) recibe **403** automáticamente.
> - Solo `/api/auth/2fa/verify` usa `ability:2fa_pending`.
>
> Por tanto, `RequireFullAuth` era **redundante** y se removió para evitar una capa extra de middleware (principio KISS). Se documenta aquí por trazabilidad.

Cuando un usuario con 2FA habilitado hace login (por email o socialite), recibe un **token parcial** con ability `['2fa_pending']`. Este token solo debe poder acceder a `/api/auth/2fa/verify`.

Si las rutas protegidas usan solo `auth:sanctum` (que solo valida que el token exista), el token `2fa_pending` accedería a **todo** — el 2FA queda bypasseado. Por eso las rutas protegidas usan `ability:*` (que cubre exactamente el caso que antes cubría `RequireFullAuth`).

### Código completo

**Archivo:** `app/Http/Middleware/RequireFullAuth.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso a rutas protegidas si el token tiene solo
 * ability '2fa_pending'.
 *
 * Uso: agregar como middleware en las rutas protegidas que NO
 * sean /auth/2fa/verify.
 *   Route::middleware(['auth:sanctum', 'ability:*', RequireFullAuth::class])
 */
class RequireFullAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Si no hay user autenticado, dejar que auth:sanctum maneje el 401
        if (! $user) {
            return $next($request);
        }

        // Si el token solo tiene '2fa_pending' (no tiene '*'), rechazar
        if (! $user->tokenCan('*')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Verificación de 2FA requerida.',
            ], 403);
        }

        return $next($request);
    }
}
```

### Aplicación en rutas (Paso 10)

> El middleware `RequireFullAuth` ya no existe. Las rutas protegidas usan **dos middlewares**:

```php
Route::middleware(['auth:sanctum', 'ability:*'])->group(function () {
    // ... todas las rutas protegidas (excepto /auth/2fa/verify)
    // Un token con ability '2fa_pending' es rechazado con 403 por 'ability:*'.
});
```

El endpoint `/auth/2fa/verify` usa SOLO `ability:2fa_pending` (no `RequireFullAuth`):

```php
Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
    ->middleware(['auth:sanctum', 'ability:2fa_pending']);
```

### Patrones aplicados

- ✅ **§2 SRP**: el middleware hace UNA sola cosa (rechazar tokens pendientes en rutas protegidas).
- ✅ **§18 YAGNI**: solo bloquea el caso `2fa_pending` — no verifica todas las abilities posibles.

---

## Paso 10: Rutas Completas

### ¿Qué rutas necesitamos?

Agrupamos las rutas en tres categorías:

| Categoría | Middleware | Endpoints |
|-----------|-----------|-----------|
| **Públicas** | Ninguno | register, login, social redirect/callback, forgot-password, reset-password, email verify |
| **Protegidas GET** | `auth:sanctum` + `ability:*` | — |
| **Protegidas POST/PATCH/DELETE** | `auth:sanctum` + `ability:*` + `throttle:10,1` | logout, 2fa enable/confirm/disable, email verification notification |
| **2FA pendiente** | `auth:sanctum` + `ability:2fa_pending` | 2fa verify |

### Código completo

**Archivo:** `routes/api.php`

```php
<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChannelMessageController;
use App\Http\Controllers\ClubCategoryController;
use App\Http\Controllers\ClubChannelController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubMemberController;
use App\Http\Controllers\ClubMemberRoleController;
use App\Http\Controllers\ClubRoleController;
use App\Http\Controllers\DmConversationController;
use App\Http\Controllers\DmMessageController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ============================================================
// RUTAS PÚBLICAS (sin autenticación)
// ============================================================
Route::prefix('auth')->group(function () {
    // ─── Registro y Login ─────────────────────────────────
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // ─── Socialite (OAuth2) ────────────────────────────────
    Route::get('/social/{provider}', [SocialiteController::class, 'redirect'])
        ->where('provider', 'google|github|discord');
    Route::get('/social/{provider}/callback', [SocialiteController::class, 'callback'])
        ->where('provider', 'google|github|discord');

    // ─── Password Reset ────────────────────────────────────
    Route::post('/forgot-password', ForgotPasswordController::class);
    Route::post('/reset-password', ResetPasswordController::class);

    // ─── Email Verification ────────────────────────────────
    // El enlace de verificación llega sin autenticación
    // (el usuario hace clic desde su email)
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->name('verification.verify');

    // ─── 2FA Verify (token pendiente) ──────────────────────
    // Este endpoint requiere token ability '2fa_pending'
    Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
        ->middleware(['auth:sanctum', 'ability:2fa_pending']);
});

// ============================================================
// RUTAS PROTEGIDAS (auth:sanctum)
// ============================================================
Route::middleware(['auth:sanctum', 'ability:*'])->group(function () {

    // ============================================================
    // RUTAS DE LECTURA (GET) — Sin rate limiting
    // ============================================================

    // -- Usuario --
    Route::get('/user', [UserController::class, 'me']);
    Route::get('/user/sessions', [UserController::class, 'sessions']);
    Route::get('/users/{user}', [UserController::class, 'show']);

    // -- Amistades --
    Route::get('/user/friends', [FriendshipController::class, 'index']);
    Route::get('/user/friend-requests', [FriendshipController::class, 'pending']);

    // -- Notificaciones --
    Route::get('/notifications', [NotificationController::class, 'index']);

    // -- Clubes --
    Route::get('/user/clubs', [ClubController::class, 'index']);
    Route::get('/clubs/{club}/preview', [ClubController::class, 'preview']);
    Route::get('/clubs/{club}', [ClubController::class, 'show']);

    // -- Miembros --
    Route::get('/clubs/{club}/members', [ClubMemberController::class, 'index']);

    // -- Roles --
    Route::get('/clubs/{club}/roles', [ClubRoleController::class, 'index']);

    // -- Categorías --
    Route::get('/clubs/{club}/categories', [ClubCategoryController::class, 'index']);

    // -- Canales --
    Route::get('/clubs/{club}/channels', [ClubChannelController::class, 'index']);

    // -- Mensajes --
    Route::get('/channels/{channel}/messages', [ChannelMessageController::class, 'index']);

    // -- DM --
    Route::get('/user/dm-conversations', [DmConversationController::class, 'index']);
    Route::get('/dm-conversations/{dm_conversation}', [DmConversationController::class, 'show']);
    Route::get('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'index']);

    // -- Dashboard y Búsqueda --
    Route::get('/explore', [ExploreController::class, 'index']);
    Route::get('/search', [SearchController::class, 'index']);

    // ============================================================
    // RUTAS DE ESCRITURA (POST/PATCH/DELETE) — Rate limited
    // ============================================================
    Route::middleware('throttle:10,1')->group(function () {

        // ─── Autenticación ─────────────────────────────────
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // ─── 2FA Management ────────────────────────────────
        Route::post('/auth/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('/auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('/auth/2fa/disable', [TwoFactorController::class, 'disable']);

        // ─── Email Verification (reenvío) ──────────────────
        Route::post('/auth/email/verification-notification', [EmailVerificationController::class, 'notification']);

        // ─── Usuario ───────────────────────────────────────
        Route::patch('/user', [UserController::class, 'updateProfile']);

        // ─── Amistades ─────────────────────────────────────
        Route::post('/user/friend-requests', [FriendshipController::class, 'store']);
        Route::patch('/user/friend-requests/{request_uuid}', [FriendshipController::class, 'respond']);

        // ─── Notificaciones ────────────────────────────────
        Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);

        // ─── Clubes ────────────────────────────────────────
        Route::post('/clubs', [ClubController::class, 'store']);
        Route::patch('/clubs/{club}', [ClubController::class, 'update']);
        Route::delete('/clubs/{club}', [ClubController::class, 'destroy']);

        // ─── Miembros ──────────────────────────────────────
        Route::post('/clubs/{club}/members', [ClubMemberController::class, 'store']);
        Route::delete('/clubs/{club}/members/{member}', [ClubMemberController::class, 'destroy']);

        // ─── Roles ─────────────────────────────────────────
        Route::post('/clubs/{club}/roles', [ClubRoleController::class, 'store']);
        Route::patch('/clubs/{club}/roles', [ClubRoleController::class, 'update']);
        Route::delete('/clubs/{club}/roles/{role}', [ClubRoleController::class, 'destroy']);
        Route::post('/clubs/{club}/members/{user}/roles', [ClubMemberRoleController::class, 'store']);

        // ─── Categorías ────────────────────────────────────
        Route::post('/clubs/{club}/categories', [ClubCategoryController::class, 'store']);
        Route::patch('/clubs/{club}/categories', [ClubCategoryController::class, 'update']);
        Route::delete('/clubs/{club}/categories/{category}', [ClubCategoryController::class, 'destroy']);

        // ─── Canales ───────────────────────────────────────
        Route::post('/clubs/{club}/channels', [ClubChannelController::class, 'store']);
        Route::patch('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'update']);
        Route::delete('/clubs/{club}/channels/{channel}', [ClubChannelController::class, 'destroy']);

        // ─── Mensajes ──────────────────────────────────────
        Route::post('/channels/{channel}/messages', [ChannelMessageController::class, 'store']);

        // ─── DM ────────────────────────────────────────────
        Route::post('/dm-conversations', [DmConversationController::class, 'store']);
        Route::post('/dm-conversations/{dm_conversation}/messages', [DmMessageController::class, 'store']);
    });
});
```

### Mapa de rutas de autenticación

```
PÚBLICAS (sin token):
  POST   /api/auth/register
  POST   /api/auth/login
  GET    /api/auth/social/{provider}
  GET    /api/auth/social/{provider}/callback
  POST   /api/auth/forgot-password
  POST   /api/auth/reset-password
  GET    /api/auth/email/verify/{id}/{hash}

CON TOKEN PENDIENTE (ability:2fa_pending):
  POST   /api/auth/2fa/verify

PROTEGIDAS (ability:* + throttle:10,1):
  POST   /api/auth/logout
  POST   /api/auth/2fa/enable
  POST   /api/auth/2fa/confirm
  POST   /api/auth/2fa/disable
  POST   /api/auth/email/verification-notification
```

### ¿Por qué el 2FA verify está fuera del grupo protegido principal?

Porque el endpoint `2fa/verify` requiere un token con ability `['2fa_pending']`, NO `['*']`. Si lo pusieramos dentro del grupo `ability:*`, los tokens pendientes no podrían acceder.

```php
Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])
    ->middleware(['auth:sanctum', 'ability:2fa_pending']);
```

Esto es intencional: el token pendiente SOLO puede acceder a este endpoint. Ni siquiera puede acceder a `/api/user`.

### Patrones aplicados

- ✅ **§2 SRP**: Rutas definen URL + middleware. No hay lógica en `api.php`.
- ✅ **§18 YAGNI**: Solo se exponen los endpoints necesarios. Rate limiting en rutas de escritura.
- ✅ **§10 Octane**: Sin `broadcast()` síncrono. Sin propiedades estáticas.

---

## 📋 Checklist de Verificación Final

### Migraciones y Base de Datos

- [x] `php artisan migrate` ejecutado sin errores (28/28 migraciones aplicadas, incluida I3 `add_unique_provider_provider_id_to_users`)
- [x] Nueva migración `add_socialite_and_2fa_fields_to_users` aplicada
- [x] Verificar columnas en `users`: `provider`, `provider_id`, `provider_token`, `provider_refresh_token`, `two_factor_enabled`, `two_factor_backup_codes`
- [x] Verificar índice compuesto en `['provider', 'provider_id']`

### Paquetes Composer

- [x] `composer show laravel/socialite` — instalado
- [x] `composer show pragmarx/google2fa` — instalado
- [x] `composer show bacon/bacon-qr-code` — instalado
- [x] Configurar `config/services.php` con Google, GitHub, Discord (según corresponda)

### Archivos Creados

- [x] `app/Http/Resources/AuthResource.php`
- [x] `database/migrations/02_users/2026_07_05_000001_make_username_first_name_last_name_nullable_in_users.php`
- [x] `database/migrations/02_users/2026_07_05_000002_add_unique_constraint_to_username_in_users.php`
- [x] `app/Http/Controllers/Auth/SocialiteController.php`
- [x] `app/Http/Controllers/Auth/ForgotPasswordController.php`
- [x] `app/Http/Controllers/Auth/ResetPasswordController.php`
- [x] `app/Http/Controllers/Auth/TwoFactorController.php`
- [x] `app/Http/Controllers/Auth/EmailVerificationController.php`
- [x] `app/Http/Requests/Auth/ForgotPasswordRequest.php`
- [x] `app/Http/Requests/Auth/ResetPasswordRequest.php`
- [x] `app/Http/Requests/Auth/EnableTwoFactorRequest.php`
- [x] `app/Http/Requests/Auth/ConfirmTwoFactorRequest.php`
- [x] `app/Http/Requests/Auth/VerifyTwoFactorRequest.php`
- [x] `app/Http/Requests/Auth/DisableTwoFactorRequest.php`
- [x] `app/Http/Requests/Auth/CompleteProfileRequest.php`
- [~] `app/Http/Middleware/RequireFullAuth.php` — **Eliminado**: el middleware `ability:*` en el grupo de rutas protegidas ya bloquea tokens con ability `2fa_pending` (devuelve 403), por lo que `RequireFullAuth` era redundante. Ver Paso 9.5.

### Archivos Modificados

- [x] `app/Models/User.php` — nuevos campos en `#[Fillable]`, métodos 2FA, `casts()` actualizado. `username`/`first_name`/`last_name` son nullable.
- [x] `app/Http/Controllers/AuthController.php` — `AuthResource`, detección 2FA en login, token abilities. `register()` solo persiste los 5 campos del form.
- [x] `app/Http/Controllers/SocialiteController.php` — callback con try-catch + check 2FA. Crea user con username=null para OAuth. *(Nota: movido a `app/Http/Controllers/Auth/SocialiteController.php`)*
- [x] `app/Http/Controllers/UserController.php` — método `completeProfile()` para `PATCH /api/user/complete-profile`.
- [x] `app/Http/Resources/UserResource.php` — sin campos extra en la respuesta.
- [x] `app/Http/Resources/AuthResource.php` — incluye `profile_completed: bool` para que el frontend sepa si redirigir a /auth/complete-profile.
- [x] `routes/api.php` — todas las rutas nuevas + `PATCH /user/complete-profile`

### Verificación de Rutas

- [x] `php artisan route:list` muestra todas las rutas nuevas (61 rutas registradas)
- [x] Las rutas públicas no requieren token
- [x] `POST /api/auth/2fa/verify` tiene middleware `ability:2fa_pending`
- [x] Las rutas protegidas tienen middleware `ability:*` (reemplaza a `RequireFullAuth`, ya eliminado)
- [x] Rate limiting `throttle:10,1` en rutas de escritura

### Verificación de Seguridad

- [ ] `two_factor_secret` no se expone en ningún Resource
- [ ] `two_factor_backup_codes` no se expone en ningún Resource
- [ ] Backup codes se guardan hasheados con bcrypt
- [ ] Backup codes se muestran en texto plano solo una vez (en confirm)
- [ ] ForgotPassword siempre responde success (no revela si el email existe)
- [ ] Password reset tiene token de un solo uso
- [ ] Rate limiting en todos los endpoints de escritura

### Verificación de Octane

- [ ] Sin propiedades estáticas mutables en controllers/services
- [ ] Sin `broadcast()` síncrono en controllers (no aplica en auth layer)
- [ ] Sin `request()->user()` o `auth()->id()` en modelos

### Verificación de YAGNI

- [ ] `AuthResource::requires_2fa` solo se incluye cuando es `true`
- [ ] Backup codes se generan solo cuando se confirma 2FA
- [ ] QR se genera solo cuando se pide (enable)
- [ ] No hay campos extra en las respuestas

---

## 📚 Referencias

- [Guía conceptual de autenticación](01_concepts_and_architecture.md) — Lee esta guía primero si no entiendes algún concepto
- [docs/architecture/mandatory_patterns.md](../../architecture/mandatory_patterns.md) — Patrones obligatorios del proyecto
- [docs/api_contract.md](../../api_contract.md) — Contrato de API
- [Laravel Sanctum Documentation](https://laravel.com/docs/13.x/sanctum)
- [Laravel Socialite Documentation](https://laravel.com/docs/13.x/socialite)
- [pragmarx/google2fa](https://github.com/antonioribeiro/google2fa)
- [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode)

---

> **Fin de la guía de implementación backend de autenticación.**  
> Creada para la rama `feature/auth` del proyecto Vyntra.  
> Sigue el orden de los pasos y verifica cada checklist antes de avanzar.  
> Próxima guía: `03_frontend_implementation.md`
