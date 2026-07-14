# Reporte de Testeo y Hallazgos de Seguridad — Sistema de Autenticación (Vyntra Backend)

> **Alcance:** Auth completo (register/login/logout, 2FA TOTP, Socialite google/github/discord, password reset, email verification, complete-profile)
> **Stack:** Laravel 13 + Sanctum 4 + Socialite + pragmarx/google2fa + bacon-qr-code + Octane/Swoole + PostgreSQL
> **Fecha:** 2026-07-14
> **Orquestador:** @agente-de-testeo · Subagentes: @testExecutor, @CreateTester, @TesterRequests, @breakerTester
> **Nota de alcance:** la configuración de infraestructura (PgBouncer, `compose.yaml`, variables de entorno del contenedor) **no está incluida en esta branch**, por lo que los problemas de infra quedan fuera de este reporte. El testeo se validó vía PHPUnit contra la DB de test configurada en `phpunit.xml`.

---

## 0. Resumen Ejecutivo

- **Infraestructura:** `vendor/` instalado ✅ · `.env` con OAuth creds de google/github/discord + `APP_KEY` ✅ · PostgreSQL y Redis disponibles ✅. *La configuración de infraestructura no está en esta branch → fuera de alcance.*
- **Tests:** baseline `19/19` pass · security tests `13/13` pass tras aplicar mitigaciones (ver §9).
- **Vulnerabilidades (código):** 2 CRÍTICAS, 1 ALTA, 2 MEDIAS, varias BAJAS.
- **Arquitectura de auth:** sólida en el núcleo (2FA, abilities, revocación, anti-mass-assignment, anti-SQLi). Los problemas son de **configuración de endpoints públicos** y **exposición de token**, no del diseño base.

---

## 1. Infraestructura (fuera de alcance de esta branch)

> ⚠️ La configuración de infraestructura (PgBouncer, `compose.yaml`, variables de entorno del contenedor Octane) **no está incluida en esta branch**. Los problemas relacionados con ella (p. ej. PgBouncer caído, `DB_HOST=pgbouncer`) **no aplican a este reporte** y deben evaluarse en la branch de infra/despliegue correspondiente. El testeo funcional de auth se validó vía PHPUnit contra la DB de test configurada en `phpunit.xml`.

---

## 2. Vulnerabilidades de Seguridad

| Severidad | Vulnerabilidad | Ubicación | Solución |
|---|---|---|---|
| 🔴 CRÍTICA | Email enumeration en forgot-password | `ForgotPasswordRequest.php:34` | Quitar `exists:users,email` |
| 🔴 CRÍTICA | Sin rate limiting en login | `routes/api.php:40` | Agregar `throttle:10,1` |
| 🟠 ALTA | Sin rate limiting en register | `routes/api.php:39` | Agregar `throttle` |
| 🟠 MEDIA | XSS potencial en campos de usuario | `UserResource` | Validar `regex:/^[^<>]+$/` |
| 🟠 MEDIA | Race condition en Socialite | `SocialiteController::findOrCreateSocialUser()` | `try/catch (UniqueConstraintViolationException)` |
| 🟡 BAJA | `$fillable` expone campos sensibles | `User.php` `#[Fillable]` | Mover a Service/Action |
| 🟡 BAJA | Tokens `2fa_pending` no se limpian | `TwoFactorController::verify()` | Revocar todos los pendientes |
| 🟡 BAJA | Doc contradice implementación en forgot | `ForgotPasswordController` docblock | Alinear doc/code |

### 🔴 CRÍTICA 1 — Email Enumeration en Forgot Password
- **Dónde:** `app/Http/Requests/Auth/ForgotPasswordRequest.php:34` → `'email' => ['required', 'email', 'exists:users,email']`
- **Por qué es bug:** el controller `ForgotPasswordController` responde **siempre 200** (protección anti-enumeración), pero el FormRequest corta antes con `422` si el email no existe. La regla `exists:` anula la protección.
- **Evidencia:** el test `test_forgot_password_with_nonexistent_email_returns_422` **pasa** → confirma que un atacante distingue emails válidos de inválidos por el status de respuesta.
- **Solución:** cambiar a `['required', 'email']`. `Password::sendResetLink()` ya maneja internamente el caso inexistente y el controller ignora el status.

### 🔴 CRÍTICA 2 — Sin Rate Limiting en Login
- **Dónde:** `routes/api.php:40` — `/login` está en el grupo **PÚBLICO** (líneas 37-65) sin `throttle`. El `throttle:10,1` solo está en rutas protegidas (línea 120).
- **Evidencia:** el test `test_login_rate_limits_after_10_attempts` **falla** (11 intentos → todos `422`, ningún `429`); `@breakerTester` lanzó 25 intentos sin bloqueo.
- **Impacto:** fuerza bruta ilimitada. Combinado con CRÍTICA 1 → ataque dirigido de diccionario.
- **Solución:** `Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');`

### 🟠 ALTA — Sin Rate Limiting en Register
- **Dónde:** `routes/api.php:39`. Mismo root cause que la CRÍTICA 2. Permite creación masiva de cuentas (spam, agotamiento de usernames/emails).
- **Solución:** `Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,60');`

### 🟠 MEDIA — XSS potencial en campos de usuario (riesgo frontend)
- **Dónde:** `UserResource` devuelve `username`, `first_name`, `last_name`, `bio` sin sanitizar; el backend los guarda raw.
- **Realidad:** no es XSS de backend per se, pero **si el frontend renderiza estos campos como HTML** (ej. `dangerouslySetInnerHTML` en React, `v-html` en Vue) un atacante puede robar token/cookies. React escapa por defecto con `{}`, así que depende del FE.
- **Solución defensiva:** validar con `regex:/^[^<>]+$/` en `StoreUserRequest`/`CompleteProfileRequest`, y que el FE nunca use HTML crudo para estos campos.

### 🟠 MEDIA — Race Condition en Socialite (robustez)
- **Dónde:** `SocialiteController::findOrCreateSocialUser()` hace `User::create()` sin `try-catch`, pese al `unique(['provider', 'provider_id'])` (migración `...000003`).
- **Impacto:** dos callbacks OAuth concurrentes para un usuario nuevo → el 2º lanza `UniqueConstraintViolationException` → `500`. No hay duplicación (la DB lo previene) pero es un error feo para el usuario.
- **Solución:**
  ```php
  use Illuminate\Database\UniqueConstraintViolationException;
  try {
      return User::create([...]);
  } catch (UniqueConstraintViolationException $e) {
      return User::where('provider', $provider)
          ->where('provider_id', (string) $socialUser->id)
          ->firstOrFail();
  }
  ```

### 🟡 BAJA — `$fillable` expone campos sensibles
- `two_factor_secret`, `two_factor_enabled`, `two_factor_backup_codes`, `provider*`, están en `#[Fillable]` (`User.php`). Hoy **NO es explotable** (los controllers extraen campos explícitos; `@breakerTester` confirmó "mass assignment: No roto"), pero es defensa-en-profundidad: un futuro `$user->update($request->validated())` sin filtrar los filtraría.
- **Solución:** mover estos campos a un `Service`/`Action` explícito en vez de depender de mass-assignment.

### 🟡 BAJA — Múltiples tokens `2fa_pending` no se limpian
- En `TwoFactorController::verify()` solo se borra `currentAccessToken()`. Tokens pendientes de otros dispositivos quedan vivos (solo sirven para `/2fa/verify`, bajo TOTP).
- **Solución:** al verificar, revocar todos: `$user->tokens()->where('name', '2fa_pending')->delete();`

### 🟡 BAJA — Contradicción doc/implementación en ForgotPassword
- El docblock de `ForgotPasswordController` dice "siempre 200" pero la regla `exists:` lo contradice (mismo origen que CRÍTICA 1). Alinear docblock o quitar `exists`.

---

## 3. Riesgos de Integración con el Frontend

| Riesgo | Severidad | Detalle |
|---|---|---|
| 🔴 Token de Socialite en URL query | ALTA | `/auth/callback?token=...` expuesto en logs del server, historial del navegador y header `Referer`. El FE debe leerlo de `window.location` inmediatamente y no persistirlo en URL. Mejor: fragmento `#token=` o POST. Confirmado en `SocialiteController::callback`. |
| 🟠 XSS en campos | MEDIA | Ver MEDIA en sección 2. El FE debe escapar `username`/`bio`. |
| 🟡 302 + HTML sin `Accept: application/json` | BAJA | Errores de validación devuelven 302 HTML si el cliente no manda `Accept`. El FE debe siempre enviar ese header. |
| ✅ CORS | OK | `@TesterRequests` confirmó `Access-Control-Allow-Origin: http://localhost:3000` + `Allow-Credentials: true` en preflight (204) y en errores. Listo para el SPA. |
| ✅ Contrato AuthResource | OK | `{user, token, token_type:'Bearer', profile_completed, requires_2fa?}` es consistente y minimalista (YAGNI). El FE usa `profile_completed`→`/complete-profile` y `requires_2fa`→pantalla 2FA. |

---

## 4. Inconsistencias de Código

1. **Columnas huérfanas:** `two_factor_recovery_codes` + `two_factor_confirmed_at` (migración `...143622`) existen pero el modelo **NO las usa** (usa `two_factor_enabled` + `two_factor_backup_codes`). Schema drift → considerar dropearlas.
2. **Sin `User` Policy:** el proyecto registra policies para `Club/*` (`AppServiceProvider`), pero auth self-service usa guard/ability en vez de Gate/Policy (`mandatory_patterns.md` §8 espera policies para ownership). No es bug, pero es divergencia de patrón.
3. **`login()` usa `Auth::guard('web')->validate()`** en vez de `Auth::attempt()` (divergencia documentada por incompatibilidad guard/sanctum). Funciona pero no estándar.
4. **Reset auto-emite token** (logea automáticamente tras reset). No estándar pero intencional.
5. **PHPUnit 12 removió la anotación `/** @test */`:** los tests nuevos deben usar prefijo `test_*` o atributo `#[Test]`. (`SecurityEdgeCasesTest` se generó con `@test` y 0 corrieron; se corrigió a `test_`.)

---

## 5. Lo que NO se rompió (resultados positivos)

`@breakerTester` confirmó sólido:
- 2FA bypass → **no roto** (token `2fa_pending` → 403 en `/api/user`, `/api/auth/logout`, `/api/auth/2fa/enable`)
- Escalada de abilities → **no roto**
- Reuso de backup codes → **no roto**
- Mass-assignment en register → **no roto**
- SQL injection → **no roto** (Eloquent + PDO parametrizado)
- Timing attack en login → **no roto** (diferencia ~16ms, no práctica)
- Revocación post-logout → **no roto** (token → 403 tras logout)
- Rate-limit en rutas protegidas → **funciona** (429 tras 10 requests)

---

## 6. Resultados de Tests

### Baseline (suite existente) — `@testExecutor`
```
php artisan test tests/Feature/Auth  →  19/19 PASSED ✅  (59 assertions)
AuthFlow 4 · TwoFactor 3 · Socialite 2 · PasswordReset 4 · EmailVerification 4 · CompleteProfile 2
```

### Security Edge Cases (nuevos) — `@testExecutor` + mitigaciones aplicadas
```
php artisan test tests/Feature/Auth/SecurityEdgeCasesTest.php
→ 13 tests · 13 PASSED · 0 FAILED · 37 assertions
```

| Test | Resultado | Veredicto |
|---|---|---|
| `test_pending_token_cannot_access_user_endpoint` | ✅ | Gate de ability OK |
| `test_backup_code_cannot_be_reused` | ✅ | Reuso bloqueado |
| `test_register_ignores_sensitive_fields` | ✅ | No mass-assignment |
| `test_complete_profile_ignores_sensitive_fields` | ✅ | No mass-assignment |
| `test_forgot_password_with_existing_email_returns_200` | ✅ | — |
| `test_forgot_password_with_nonexistent_email_returns_200` | ✅ | **Mitigado**: siempre 200 (sin enumeración) |
| `test_reset_with_invalid_token_returns_400` | ✅ | — |
| `test_reset_with_email_mismatch_returns_400` | ✅ | — |
| `test_email_verify_with_manipulated_hash_returns_403` | ✅ | Signed URL OK |
| `test_email_verify_without_signature_returns_403` | ✅ | Signed URL OK |
| `test_pending_token_cannot_logout` | ✅ | Gate OK |
| `test_login_rate_limits_after_10_attempts` | ✅ | Throttle aplicado (429 en intento #11) |
| `test_full_token_cannot_bypass_2fa_without_code` | ✅ | 422 correcto (sin escalar privilegios) |

**Nota de falso positivo (resuelto):** el test original `test_full_token_cannot_access_2fa_verify` esperaba `403`, pero un token `['*']` de Sanctum satisface la ability `2fa_pending` y *alcanza* el controller, recibiendo `422` por falta del `code`. No es escalada: el endpoint exige un TOTP/backup code válido. Se renombró a `test_full_token_cannot_bypass_2fa_without_code` y se asserta `422` (no bypass sin código). El control real de 2FA sigue siendo el gate de abilities (ver `test_pending_token_cannot_access_user_endpoint`).

---

## 7. Archivos de Test Creados (no modifican `app/`)

- `tests/Feature/Auth/SecurityEdgeCasesTest.php` — 13 tests de seguridad/edge.
- `tests/manual/auth_http_probe.md` — script HTTP paso a paso contra `http://localhost:8000`.
- `docs/plans/auth_testing_plan.md` — plan de testeo (fases, cobertura, extensión).

---

## 8. Estado de Soluciones Aplicadas

| # | Sev. | Solución | Estado | Detalle |
|---|---|---|---|---|
| 1 | 🔴 | Quitar `exists:users,email` de `ForgotPasswordRequest` | ✅ Aplicada | Elimina enumeración; el endpoint ahora siempre 200 (genérico). |
| 2 | 🔴 | `throttle:10,1` en `/login` | ✅ Aplicada | Fuerza bruta limitada a 10/min. |
| 3 | 🟠 | `throttle` en `/register` | ✅ Aplicada | `throttle:5,60` (5 regs/hora). |
| 4 | 🟠 | `try/catch` en `findOrCreateSocialUser()` | ✅ Aplicada | Re-lee el user si hay race condition. |
| 5 | 🟠 | `regex:/^[^<>]+$/` en username/first/last_name | ✅ Aplicada | Defensa XSS en `StoreUserRequest` + `CompleteProfileRequest`. |
| 6 | 🟡 | Limpiar tokens `2fa_pending` en `verify()` | ✅ Aplicada | Revoca todos los pendientes del user. |
| 7 | 🟡 | `#[Fillable]` → Service/Action | ⏸️ No aplicada (decisión) | No explotable hoy; usuario descartó el cambio (KISS/YAGNI). |
| 8 | 🟡 | Token Socialite a fragmento `#` | ✅ Aplicada | `?token=` → `#token=` en `callback()`. |
| 9 | 🟡 | Dropear columnas huérfanas | ✅ Aplicada (migración) | `2026_07_14_000000_drop_orphaned_two_factor_columns.php`. Requiere `php artisan migrate`. |

---

## 9. Soluciones Aplicadas — Detalle (cómo y por qué)

### 9.1 Quitar `exists:users,email` (CRÍTICA 1 — enumeración)
- **Cómo:** en `app/Http/Requests/Auth/ForgotPasswordRequest.php` se cambió
  `'email' => ['required', 'email', 'exists:users,email']` → `'email' => ['required', 'email']`.
- **Por qué:** el controller `ForgotPasswordController` ya devuelve **200 siempre** (mensaje genérico)
  para no revelar si el email existe. La regla `exists:` cortaba antes con **422**, anulando esa
  protección y permitiendo enumeración. Al quitarla, el flujo llega al controller y
  `Password::sendResetLink()` maneja internamente el caso inexistente sin diferenciar la respuesta.
  El test `test_forgot_password_with_nonexistent_email_returns_200` valida el nuevo comportamiento.

### 9.2 Throttle en `/login` (CRÍTICA 2) y `/register` (ALTA)
- **Cómo:** en `routes/api.php`:
  - `Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,60');`
  - `Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');`
- **Por qué:** ambas rutas estaban en el grupo **público** sin límite, permitiendo fuerza bruta de
  credenciales y creación masiva de cuentas. El `throttle` de Laravel usa la cache (Redis) por IP.
  `10,1` = 10 intentos/min en login; `5,60` = 5 registros/hora (más estricto: el spam de cuentas es
  de bajo volumen pero alto impacto). El test `test_login_rate_limits_after_10_attempts` ahora pasa
  (429 en el intento #11).

### 9.3 `try/catch` en `findOrCreateSocialUser()` (MEDIA — race condition)
- **Cómo:** se importó `Illuminate\Database\UniqueConstraintViolationException` y se envolvió el
  `User::create()` en `try/catch`; ante la excepción se re-lee el usuario existente por
  `(provider, provider_id)`.
- **Por qué:** la migración define `unique(['provider','provider_id'])`. Dos callbacks OAuth
  concurrentes para un usuario nuevo podían lanzar `UniqueConstraintViolationException` → **500**.
  El catch hace el flujo **idempotente**: el segundo request recupera el registro ya creado en lugar
  de fallar. No hay duplicación (la DB lo garantiza) y el usuario no ve un error.

### 9.4 `regex:/^[^<>]+$/` en campos de usuario (MEDIA — XSS defensiva)
- **Cómo:** en `StoreUserRequest` y `CompleteProfileRequest` se agregó
  `'regex:/^[^<>]+$/'` a `username`, `first_name` y `last_name`.
- **Por qué:** el backend guarda estos campos raw y `UserResource` los devuelve sin sanitizar. Si el
  FE alguna vez renderiza como HTML crudo (ej. `dangerouslySetInnerHTML`), un payload `<script>` /
  `<img onerror>` podría ejecutarse. El regex bloquea `<` y `>` a nivel de validación (defensa en
  profundidad) sin afectar usernames/names legítimos. El FE sigue debiendo escapar por su lado.

### 9.5 Limpiar tokens `2fa_pending` en `verify()` (BAJA)
- **Cómo:** en `TwoFactorController::verify()` se agregó
  `$user->tokens()->where('name', '2fa_pending')->delete();` tras revocar el token actual.
- **Por qué:** antes solo se borraba `currentAccessToken()`. Si el usuario iniciaba 2FA en dos
  dispositivos, el token pendiente del otro quedaba vivo. No es una fuga (solo sirve para
  `/2fa/verify` bajo TOTP), pero es limpieza correcta: al completar 2FA en uno, los pendientes
  residuales se invalidan.

### 9.6 Token de Socialite en fragmento `#` (BAJA / integración)
- **Cómo:** en `SocialiteController::callback()` se cambió `'/auth/callback?token='` →
  `'/auth/callback#token='` tanto para el caso 2FA como el token completo.
- **Por qué:** el token en query string viaja en logs del servidor web, header `Referer` e historial
  del navegador. En el fragmento (`#`) el token **nunca se envía al server** ni se registra en
  logs/Referer. El FE debe leer `window.location.hash` inmediatamente. *Requiere coordinación FE
  para parsear el fragmento en lugar del query string.*

### 9.7 Dropear columnas huérfanas (BAJA — schema drift)
- **Cómo:** nueva migración
  `database/migrations/02_users/2026_07_14_000000_drop_orphaned_two_factor_columns.php` que hace
  `Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['two_factor_recovery_codes','two_factor_confirmed_at']))`;
  `down()` las re-crea.
- **Por qué:** esas columnas fueron añadidas por una migración previa pero el modelo `User` **no las
  usa** (usa `two_factor_enabled` + `two_factor_backup_codes`). Eran schema drift y, peor, estaban en
  `#[Fillable]`, constituyendo superficie de mass-assignment muerta. Al dropearlas se elimina esa
  superficie y se alinea el schema con el modelo. **Nota:** requiere ejecutar `php artisan migrate`
  (no se corrió en este entorno por falta de acceso a la DB de la branch).

### 9.8 No aplicado: `#[Fillable]` → Service/Action
- **Por qué no:** `@breakerTester` confirmó que el mass-assignment **no es explotable hoy** (los
  controllers extraen campos explícitos). El usuario decidió no introducir el patrón Service/Action
  para tan pocos casos (principio KISS/YAGNI). Queda como mejora futura opcional.
