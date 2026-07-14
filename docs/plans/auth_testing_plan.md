# Plan de Testeo: Autenticación (Auth Layer)

> **Creado por:** @CreateTester
> **Versión:** 1.0
> **Fecha:** 2026-07-13
> **Ejecutado por:** @TesterRequests / @testExecutor

---

## 1. Objetivo

Validar la capa de autenticación del backend Vyntra (Laravel 13) cubriendo:

- Flujo feliz de registro, login, logout
- Autenticación de dos factores (2FA TOTP + backup codes)
- Restablecimiento de contraseña (forgot/reset)
- Verificación de email (signed URLs)
- Socialite OAuth (Google, GitHub, Discord)
- **Casos borde de seguridad** (mass assignment, email enumeration, rate limiting, token abilities)
- **Vulnerabilidades conocidas documentadas** (no corregir, solo testear el comportamiento actual)

---

## 2. Fases

| Fase | Descripción | Responsable |
|------|-------------|-------------|
| **1. Unit/Feature Tests** | Tests PHPUnit automatizados en `tests/Feature/Auth/` | `php artisan test` |
| **2. HTTP Probe Manual** | Script PowerShell/curl contra servidor real (`localhost:8000`) | @TesterRequests |
| **3. Auditoría de seguridad** | Revisión de resultados y reporte de vulnerabilidades | @supervision-de-proyecto → @audit |

---

## 3. Cobertura

### 3.1 Mapa de archivos de test

| Archivo | Tipo | Cubre |
|---------|------|-------|
| `AuthFlowTest.php` | Feature (PHPUnit) | Register, login sin 2FA, logout |
| `CompleteProfileTest.php` | Feature (PHPUnit) | Complete profile tras OAuth |
| `EmailVerificationTest.php` | Feature (PHPUnit) | Verify signed URL, expired, resend |
| `PasswordResetTest.php` | Feature (PHPUnit) | Forgot, reset válido, token inválido |
| `TwoFactorTest.php` | Feature (PHPUnit) | Enable, confirm, verify con TOTP, disable |
| `SocialiteTest.php` | Feature (PHPUnit) | Callback crea/vincula usuario |
| **`SecurityEdgeCasesTest.php`** | **Feature (PHPUnit) — NUEVO** | **8 casos de seguridad (ver §3.2)** |
| `auth_http_probe.md` | Manual (HTTP) | Flujo completo contra servidor real |

### 3.2 Casos de SecurityEdgeCasesTest

| # | Caso | Assert clave | Vulnerabilidad documentada |
|---|------|-------------|---------------------------|
| a | **2FA gate** | Token `['*']` → 403 en `/2fa/verify`; token `['2fa_pending']` → 403 en `/user` | — |
| b | **Backup code reuse** | 1er uso → 200; reuso → 422 | Reusable → bug de seguridad |
| c | **Mass assignment** | `two_factor_secret`, `two_factor_enabled`, `provider`, `provider_id` ignorados en register y complete-profile | — |
| d | **Email enumeration** | Email existente → 200; inexistente → 422 | **SÍ**: FormRequest revela emails registrados |
| e | **Reset inválido** | Token inválido → 400; email mismatch → 400 | — |
| f | **Email verify** | Hash manipulado → 403; sin firma → 403 | — |
| g | **Rate limiting** | 11 intentos login en <1 min → intento #11 espera 429 (hoy recibe 422) | **SÍ**: No hay throttle en `/login` |
| h | **Pending token logout** | Token `['2fa_pending']` → 403 en `/logout` | — |

### 3.3 Tabla completa de casos (todos los archivos)

| # | Caso | Archivo | Tipo | Cobertura |
|---|------|---------|------|-----------|
| 1 | Register success → 201 + token | `AuthFlowTest` | Feature | Happy path |
| 2 | Login sin 2FA → token | `AuthFlowTest` | Feature | Happy path |
| 3 | Login credenciales inválidas → 422 | `AuthFlowTest` | Feature | Error |
| 4 | Logout revoca token | `AuthFlowTest` | Feature | Happy path |
| 5 | Enable → confirm → verify → disable | `TwoFactorTest` | Feature | Happy path |
| 6 | Enable con password wrong → 422 | `TwoFactorTest` | Feature | Error |
| 7 | Verify con código inválido → 422 | `TwoFactorTest` | Feature | Error |
| 8 | OAuth complete profile | `CompleteProfileTest` | Feature | Happy path |
| 9 | Duplicate username → 422 | `CompleteProfileTest` | Feature | Auth |
| 10 | Forgot siempre 200 | `PasswordResetTest` | Feature | Happy path |
| 11 | Reset válido → 200 + password changed | `PasswordResetTest` | Feature | Happy path |
| 12 | Reset token inválido → 400 | `PasswordResetTest` | Feature | Error |
| 13 | Reset route redirect a frontend | `PasswordResetTest` | Feature | Happy path |
| 14 | Verify signed URL → email verified | `EmailVerificationTest` | Feature | Happy path |
| 15 | Verify URL expirada → 403 | `EmailVerificationTest` | Feature | Error |
| 16 | Resend notification → 200 | `EmailVerificationTest` | Feature | Happy path |
| 17 | Resend ya verificado → 400 | `EmailVerificationTest` | Feature | Error |
| 18 | Social callback crea usuario | `SocialiteTest` | Feature | Happy path |
| 19 | Social callback vincula email existente | `SocialiteTest` | Feature | Happy path |
| **20** | **Full token → 403 en 2fa/verify** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **21** | **Pending token → 403 en /user** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **22** | **Backup code reuse → 422** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **23** | **Mass assignment register** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **24** | **Mass assignment complete-profile** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **25** | **Email enumeration** | **SecurityEdgeCases** | **Security** | **Nuevo (vuln)** |
| **26** | **Reset token inválido** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **27** | **Reset email mismatch** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **28** | **Verify hash manipulado** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **29** | **Verify sin firma** | **SecurityEdgeCases** | **Security** | **Nuevo** |
| **30** | **Rate limiting login** | **SecurityEdgeCases** | **Security** | **Nuevo (vuln)** |
| **31** | **Pending token logout → 403** | **SecurityEdgeCases** | **Security** | **Nuevo** |

---

## 4. Vulnerabilidades Documentadas (WONTFIX)

Estas vulnerabilidades existen HOY en el código. Los tests las documentan pero NO las corrigen:

### 4.1 Email Enumeration (Severidad: Media)

- **Dónde:** `app/Http/Requests/Auth/ForgotPasswordRequest.php` línea 34
- **Causa:** Regla `'exists:users,email'` en el FormRequest
- **Efecto:** Email inexistente → 422; email existente → 200
- **Prueba:** `SecurityEdgeCasesTest::forgot_password_with_nonexistent_email_returns_422`
- **Solución posible:** Quitar `exists` del FormRequest y mover la verificación al controller (que ya siempre devuelve 200)

### 4.2 Falta Rate Limiting en Login (Severidad: Alta)

- **Dónde:** `routes/api.php` — `/api/auth/login` no está en el grupo `throttle:10,1`
- **Causa:** La ruta está fuera del grupo `middleware('throttle:10,1')`
- **Efecto:** Fuerza bruta ilimitada sobre login
- **Prueba:** `SecurityEdgeCasesTest::login_rate_limits_after_10_attempts` (falla porque espera 429 pero recibe 422)
- **Solución posible:** Agregar `->middleware('throttle:10,1')` a la ruta login, o moverla dentro del grupo throttle

---

## 5. Cómo ejecutar

### 5.1 Tests PHPUnit (automáticos)

```bash
# Todos los tests de auth
php artisan test --filter=Tests\\Feature\\Auth

# Test específico
php artisan test --filter=SecurityEdgeCasesTest

# Test con verbose
php artisan test --filter=SecurityEdgeCasesTest --verbose

# Reporte de vulnerabilidades esperadas (tests que fallan)
php artisan test --filter=SecurityEdgeCasesTest --verbose 2>&1 | grep "FAIL"
```

### 5.2 Tests manuales (HTTP Probe)

```powershell
# Seguir las instrucciones en tests/manual/auth_http_probe.md
# Requiere servidor corriendo en http://localhost:8000
```

---

## 6. Criterios de éxito

### 6.1 Para los tests automáticos

- **20 tests existing** (AuthFlow + TwoFactor + PasswordReset + EmailVerification + CompleteProfile + Socialite): deben pasar todos
- **8 tests nuevos** (SecurityEdgeCases): deben pasar **6 de 8**
- **Fallos esperados:**
  - `login_rate_limits_after_10_attempts`: **FALLA** (documenta falta de rate limiting)
  - Cualquier otro fallo inesperado debe reportarse como bug

### 6.2 Para los tests manuales

- Todos los pasos de `auth_http_probe.md` deben completarse sin errores 500
- Los errores 422/403/400 esperados deben coincidir con la documentación
- Las vulnerabilidades de email enumeration y rate limiting deben reproducirse manualmente

---

## 7. Cómo extender este plan

Para agregar nuevos casos de test de autenticación:

1. Identificar el caso en la tabla de cobertura (§3.3)
2. Decidir si es Feature (PHPUnit) o Manual (HTTP probe)
3. Para PHPUnit: extender `AuthTestCase` y agregar método en el archivo correspondiente
4. Para HTTP: agregar paso en `auth_http_probe.md`
5. Actualizar este plan (fecha, versión, tabla)

### Nuevos casos sugeridos para el futuro

- [ ] **Concurrent login sessions**: mismo usuario, 2 tokens activos, revocar uno
- [ ] **Token expiration**: probar que los tokens expiran según configuración Sanctum
- [ ] **OAuth account linking**: usuario logueado vincula cuenta social adicional
- [ ] **Password change invalidates sessions**: cambiar password → tokens anteriores revocados
- [ ] **Brute force con rate limiting**: si se agrega throttle, verificar que funcione
