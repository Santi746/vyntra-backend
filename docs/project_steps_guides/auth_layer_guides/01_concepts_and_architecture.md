# 🔐 Auth Layer Guide — Conceptos y Arquitectura

> **Versión:** 2.0  
> **Stack definitivo:** Sanctum 4.0 (API tokens) + Socialite (OAuth2) + 2FA TOTP (custom)  
> **NO usa:** Fortify, JWT, sesiones para API  
> **Framework:** Laravel 13  
> **Frontend:** Next.js 16 (SPA)  
> **Público:** Programadores que enfrentan Laravel auth por primera vez

---

## 📋 Índice

1. [¿Qué es cada librería y por qué la usamos?](#1-qué-es-cada-librería-y-por-qué-la-usamos)
2. [¿Qué es "Sanctum Dual"?](#2-qué-es-sanctum-dual)
3. [Token Abilities — Concepto clave para 2FA](#3-token-abilities--concepto-clave-para-2fa)
4. [Flujo OAuth2 de Socialite](#4-flujo-oauth2-de-socialite)
5. [TOTP y 2FA — Concepto](#5-totp-y-2fa--concepto)
6. [Arquitectura General](#6-arquitectura-general)
7. [¿Por qué este stack es escalable a 100k usuarios?](#7-por-qué-este-stack-es-escalable-a-100k-usuarios)
8. [Comparación con Discord / Meta / X](#8-comparación-con-discord--meta--x)
9. [Lo que aprendiste en esta guía](#9-lo-que-aprendiste-en-esta-guía)

---

## 1. ¿Qué es cada librería y por qué la usamos?

### 🛡️ Sanctum 4.0 — El sistema de autenticación principal

**¿Qué hace?**  
Sanctum es un sistema de autenticación **stateless** (sin estado). Emite tokens Bearer que el frontend (Next.js) guarda y envía en cada petición HTTP en el header `Authorization: Bearer <token>`.

**¿Cómo funciona internamente?**  
Cuando instalas Sanctum, se crea una tabla en PostgreSQL llamada `personal_access_tokens`:

```
TABLA: personal_access_tokens
┌─────────┬──────────────────┬──────────────────────────────────┬──────────────┐
│  id     │  tokenable_id    │  token (hash SHA-256)            │  abilities   │
├─────────┼──────────────────┼──────────────────────────────────┼──────────────┤
│  1      │  uuid-del-user   │  a1b2c3d4e5... (hash del token) │  ["*"]       │
└─────────┴──────────────────┴──────────────────────────────────┴──────────────┘
```

1. **Cuando el usuario hace login**: el backend genera una cadena aleatoria (el token), guarda su **hash SHA-256** en la tabla, y devuelve el token en **texto plano** al frontend.
2. **Cuando el frontend hace una petición**: envía el token en el header. Sanctum lo hashea y busca una coincidencia en la DB. Si existe, **sabe quién es el usuario**.
3. **Cuando el usuario hace logout**: el backend **borra la fila** de `personal_access_tokens`. El token deja de ser válido instantáneamente.

> [!IMPORTANT]
> Sanctum **NO usa JWT**. Usa tokens simples con hash. Esto significa que puedes **revocar tokens al instante** — solo borras la fila de la DB. Con JWT, un token robado es válido hasta que expire (no se puede revocar).

**¿Por qué es escalable?**  
Como los tokens son stateless, **cada servidor puede validarlos independientemente** sin consultar un almacén central de sesiones. Esto es CLAVE cuando escalas horizontalmente con Octane + múltiples servidores.

### 🔗 Socialite — Login con Google/GitHub/Discord

**¿Qué hace?**  
Socialite es un **cliente OAuth2** que permite a los usuarios autenticarse con proveedores externos (Google, GitHub, Discord, etc.). El usuario no necesita crear una cuenta nueva — solo hace clic en "Continuar con Google" y listo.

**¿Cómo funciona OAuth2 (en simple)?**

```
1. Usuario hace clic en "Continuar con Google"
2. Backend redirige al usuario a accounts.google.com
3. Usuario inicia sesión en Google (si no lo estaba ya)
4. Google pregunta: "¿Permitir que Vyntra vea tu email y perfil?"
5. Usuario acepta
6. Google redirige de vuelta al backend con un "código" temporal
7. Backend intercambia ese código por un "access_token" de Google
8. Backend usa ese access_token para pedir los datos del usuario a Google
9. Backend busca o crea el usuario en su propia DB
10. Backend genera un token Sanctum para ese usuario
```

**¿Por qué Socialite y no implementar OAuth2 manualmente?**  
OAuth2 es un protocolo complejo con múltiples pasos, firmas, estados y nonces. Socialite abstrae toda esa complejidad en una llamada: `Socialite::driver('google')->user()`.

### 🔢 google2fa + bacon-qr-code — Autenticación de Dos Factores (2FA)

**¿Qué hace google2fa?**  
`pragmarx/google2fa` es una librería que implementa **TOTP** (Time-based One-Time Password). Permite que el backend genere y verifique códigos de 6 dígitos que cambian cada 30 segundos.

**¿Qué hace bacon-qr-code?**  
`bacon/bacon-qr-code` genera códigos QR. Se usa para mostrarle al usuario un QR que escanea con Google Authenticator (o cualquier app compatible: Authy, 1Password, etc.).

**¿Cómo funciona TOTP? (explicación simple)**

```
Backend:                    Google Authenticator (teléfono del usuario):
─────────────────────       ──────────────────────────────────────
1. Genera un "secret"      ──────── QR ────────► 2. Escanea el QR
   (string aleatorio)                              y guarda el secret
   
3. Toma el secret +         4. Toma el mismo secret +
   tiempo actual               tiempo actual
   
5. Calcula un código         6. Calcula un código
   de 6 dígitos                de 6 dígitos
   
7. Usuario escribe          8. Backend compara: el código que
   el código que               el usuario escribió contra el
   ve en su teléfono           código que el backend calculó
   
   ──── código de 6 dígitos ────► 
   
                              9. Si coinciden → ✅ 2FA exitoso
                                 Si no coinciden → ❌ rechazado
```

> [!NOTE]
> **Backup codes**: Cuando activas 2FA, el backend genera 10 códigos de respaldo (backup codes). El usuario debe guardarlos en un lugar seguro. Si pierde el teléfono, puede usar uno de estos códigos para acceder a su cuenta. Cada backup code solo se puede usar una vez.

### ❌ ¿Por qué NO usamos Fortify?

**Fortify** es un paquete de Laravel que implementa las funcionalidades backend de autenticación (login, registro, password reset, 2FA, email verification). A primera vista suena útil, pero tiene problemas:

| Problema | Detalle |
|----------|---------|
| **Overhead de middleware** | Fortify registra múltiples middlewares que ejecutan lógica extra incluso si no la usas. En cada petición. |
| **Diseñado para Blade** | Su flujo de 2FA espera sesiones PHP y formularios Blade. Nosotros tenemos SPA con tokens. |
| **Incompatible con Sanctum Dual** | El sistema de 2FA de Fortify usa sesiones para el "pending" state. Nosotros necesitamos abilities de tokens. |
| **Personalización limitada** | Fortify tiene controladores que puedes sobrescribir, pero terminas peleando contra el paquete. |
| **Overhead cognitivo** | Para un programador nuevo, Fortify añade otra capa de abstracción: "¿Esto lo hace Fortify o mi código?". |

**Nuestra decisión:** Usamos **custom controllers** con Sanctum puro. Esto nos da:
- Control total sobre el flujo
- Sin middlewares extra
- Sin dependencias ocultas
- El programador entiende **exactamente** qué pasa en cada paso

### ❌ ¿Por qué NO usamos JWT?

| Aspecto | Sanctum (tokens con hash) | JWT (JSON Web Tokens) |
|---------|---------------------------|----------------------|
| **Revocación** | Instantánea (borras de DB) | No se puede hasta que expire |
| **Complejidad** | Baja — Sanctum lo maneja | Alta — hay que manejar refresh tokens, blacklists, expiración |
| **Validación** | Consulta a DB (con Redis cache = rápido) | El token se valida con firma criptográfica (sin DB) |
| **Estado** | Stateless (cada servidor valida contra su DB/Redis) | Stateless (cualquiera con la clave pública valida) |
| **¿Qué pasa si nos hackean la DB?** | Los tokens están hasheados — no se pueden usar | Las claves privadas pueden estar comprometidas |
| **Escalabilidad** | Con Redis cache de tokens es casi tan rápido como JWT | Excelente, pero complejo |

**Nuestra decisión:** Sanctum. La **revocación instantánea** pesa más que cualquier ventaja de JWT para nuestro caso de uso. Un token robado debe poder invalidarse inmediatamente.

---

## 2. ¿Qué es "Sanctum Dual"?

Sanctum tiene **dos modos de autenticación**:

### Modo 1: Tokens API (el principal)

```
Frontend (Next.js)                          Backend (Laravel)
       │                                          │
       │  POST /api/auth/login                    │
       │  { email, password }                     │
       │─────────────────────────────────────────►│
       │                                          │── Validar credenciales
       │                                          │── createToken('auth_token')
       │◄─────────────────────────────────────────│
       │  { token: "1|abc123...", user: {...} }   │
       │                                          │
       │  GET /api/user/sessions                  │
       │  Authorization: Bearer 1|abc123...       │
       │─────────────────────────────────────────►│
       │                                          │── Validar token (hash → DB)
       │◄─────────────────────────────────────────│
       │  { data: [...] }                         │
```

- El frontend guarda el token (Zustand store con persistencia)
- El frontend envía el token en **cada petición** en el header `Authorization`
- **Stateless**: el servidor valida el token contra la DB (o Redis cache)
- **Este es el modo que usamos el 99% del tiempo**

### Modo 2: Cookies de Sesión (solo para Socialite)

```
Navegador del usuario                      Backend (Laravel)
       │                                          │
       │  GET /api/auth/social/google             │
       │─────────────────────────────────────────►│
       │◄─────────────────────────────────────────│
       │  redirect: accounts.google.com           │
       │                                          │
       │  (usuario autentica en Google)           │
       │                                          │
       │  GET /api/auth/social/google/callback    │
       │  ?code=xxx                               │
       │─────────────────────────────────────────►│
       │                                          │── Socialite::user()
       │                                          │── Buscar/crear usuario
       │                                          │── createToken('auth_token')
       │◄─────────────────────────────────────────│
       │  redirect: frontend/auth/callback?token=xxx
```

**¿Por qué necesitamos cookies aquí?**  
Cuando el navegador es redirigido de Google de vuelta al callback, **no hay forma de pasar un header `Authorization`**. El navegador está siguiendo redirecciones HTTP — no es código JavaScript que pueda añadir headers.

**La solución:** Durante el callback de Socialite, usamos **cookies de sesión** (el segundo modo de Sanctum) para mantener la autenticación temporal. Pero esto solo ocurre **durante ese único callback**. Inmediatamente después, el backend redirige al frontend con el token en la URL, y el frontend vuelve al modo Tokens API.

> [!NOTE]
> **Sanctum Dual** no significa "usa los dos modos todo el tiempo". Significa: tokens API para la SPA (99% del tiempo), cookies SOLO durante el callback de OAuth (ese 1%).

---

## 3. Token Abilities — Concepto clave para 2FA

Sanctum permite asignar **abilities** (permisos) a cada token. Una ability es un string que describe lo que ese token puede hacer.

### Abilities más comunes

| Ability | Significado |
|---------|-------------|
| `['*']` | Token completo — puede acceder a todo (usuario verificado) |
| `['2fa_pending']` | Token limitado — solo puede verificar 2FA |
| `['email_pending']` | Token limitado — solo puede verificar email |

### Cómo se usan

```php
// Crear token con ability específica
$token = $user->createToken('2fa_pending', ['2fa_pending']);

// Crear token completo
$token = $user->createToken('auth_token', ['*']);
```

Y en las rutas, proteges los endpoints según la ability que requieran:

```php
// Solo accesible con token que tenga ability '2fa_pending'
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/2fa/verify', [TwoFactorController::class, 'verify'])
        ->middleware('ability:2fa_pending');
    
    // Requiere token completo
    Route::get('/user', [UserController::class, 'me'])->middleware('ability:*');
});
```

### Flujo completo de 2FA con Abilities

```
Usuario                           Frontend                      Backend
───────                           ───────                       ──────
                                  1. POST /api/auth/login
                                   { email, password }
                                  ──────────────────────────────►
                                                               2. Validar credenciales
                                                               3. Verificar: ¿tiene 2FA?
                                                                  ─ Sí → token ['2fa_pending']
                                                                  ─ No → token ['*']
                                  ◄──────────────────────────────
                                  4. Responde:
                                  { requires_2fa: true,
                                    token: "1|abc...",
                                    token_type: "2fa_pending" }
5. Usuario ve pantalla             
   de código 2FA                   
                                   
6. Usuario ingresa                 
   código de 6 dígitos             
                                  7. POST /api/auth/2fa/verify
                                   { code: 123456 }
                                   Authorization: Bearer 1|abc...
                                  ──────────────────────────────►
                                                               8. Validar código TOTP
                                                               9. Revocar token pendiente
                                                               10. Crear token ['*']
                                  ◄──────────────────────────────
                                  11. { token: "2|xyz...",
                                       token_type: "full" }
12. Usuario accede
    a la app completa
```

### ¿Por qué abilities y no sesiones?

Discord usa el mismo patrón: cuando tu cuenta tiene 2FA, al hacer login obtienes un "token pendiente" (llamado `mfa_token` internamente). Ese token solo sirve para verificar el código 2FA. Después de verificar, obtienes el token real.

**Ventajas de este approach:**
- **Stateless**: no necesitas guardar "este usuario está en mitad del 2FA" en Redis
- **Escalable**: cualquier servidor puede manejar cualquier estado de la autenticación
- **Consistente**: el frontend trata con tokens en todo momento, sin lógica especial de sesiones

---

## 4. Flujo OAuth2 de Socialite

### Diagrama completo paso a paso

```
FRONTEND (Next.js)              BACKEND (Laravel)           GOOGLE
      │                              │                        │
      │ 1. Usuario clic               │                        │
      │    "Continuar con Google"      │                        │
      │                              │                        │
      │ 2. redirect a:                │                        │
      │    /api/auth/social/google     │                        │
      │─────────────────────────────►│                        │
      │                              │ 3. Socialite::driver   │
      │                              │    ('google')->redirect()
      │                              │                        │
      │ 4. HTTP 302 redirect a:       │                        │
      │◄─────────────────────────────│                        │
      │    accounts.google.com        │                        │
      │    ?client_id=xxx             │                        │
      │    &redirect_uri=...          │                        │
      │    &scope=email+profile       │                        │
      │                              │                        │
      │ 5. Navegador redirige         │                        │
      │────────────────────────────────────────────────────►│
      │                              │                        │
      │                              │                        │ 6. Usuario inicia
      │                              │                        │    sesión en Google
      │                              │                        │    (si no lo estaba)
      │                              │                        │
      │                              │                        │ 7. Google pregunta:
      │                              │                        │    "Vyntra quiere ver
      │                              │                        │    tu email y perfil"
      │                              │                        │
      │                              │                        │ 8. Usuario acepta
      │                              │                        │
      │ 9. Google redirect a:         │                        │
      │    /api/auth/social/google/    │                        │
      │    callback?code=xxx&state=yyy│                        │
      │◄─────────────────────────────────────────────────────│
      │                              │                        │
      │ 10. Navegador sigue redirect  │                        │
      │─────────────────────────────►│                        │
      │                              │                        │
      │                              │ 11. Socialite::driver  │
      │                              │     ('google')->user() │
      │                              │     (intercambia code  │
      │                              │      por access_token  │
      │                              │      de Google)        │
      │                              │                        │
      │                              │ 12. ¿Usuario existe?   │
      │                              │     ┌─ Sí → actualizar │
      │                              │     └─ No → crear      │
      │                              │                        │
      │                              │ 13. createToken()      │
      │                              │                        │
      │ 14. HTTP 302 redirect a:      │                        │
      │    /auth/callback?token=xxx   │                        │
      │◄─────────────────────────────│                        │
      │                              │                        │
      │ 15. Frontend lee el token     │                        │
      │     de la URL                 │                        │
      │                              │                        │
      │ 16. Guarda token en           │                        │
      │     Zustand store             │                        │
      │                              │                        │
      │ 17. Redirect a dashboard      │                        │
```

### Explicación textual del flujo

1. **El usuario hace clic** en "Continuar con Google" en el frontend.
2. **Frontend redirige** al navegador a `/api/auth/social/google`.
3. **Backend llama a** `Socialite::driver('google')->redirect()` que genera una URL de Google con todos los parámetros necesarios (client_id, redirect_uri, scope, state).
4. **Backend responde** con un redirect HTTP 302 a `accounts.google.com`.
5. **Navegador redirige** a Google. El usuario ve la pantalla de inicio de sesión de Google.
6. **Usuario autentica** en Google (si no lo estaba ya).
7. **Google pregunta** al usuario: "Vyntra quiere ver tu email, tu perfil y tu foto. ¿Permites?"
8. **Usuario acepta** los permisos.
9. **Google redirige** de vuelta al backend a `/api/auth/social/google/callback?code=xxx&state=yyy`.
10. **Backend recibe** el código temporario (`code`).
11. **Backend intercambia** el código por un `access_token` de Google llamando a `Socialite::driver('google')->user()`. Esto hace una petición HTTP de backend-a-backend a Google.
12. **Backend busca** si ya existe un usuario con ese `provider_id` (Google) o con ese email:
    - **Si existe**: actualiza sus datos (avatar, token de Google, etc.).
    - **Si no existe**: crea un usuario nuevo con los datos que Google devolvió.
13. **Backend genera** un token Sanctum para el usuario (como en el login normal).
14. **Backend redirige** al frontend: `302 redirect a /auth/callback?token=xxx`.
15. **Frontend recibe** el token en la URL (parámetro `?token=xxx`).
16. **Frontend guarda** el token en el Zustand store.
17. **Frontend redirige** al dashboard.

### ¿Por qué el frontend necesita una página /auth/callback?

Porque el token llega como **parámetro de URL**. El frontend debe tener una página que:
1. Lea el token de `window.location.search` (o `useSearchParams()` en Next.js)
2. Lo guarde en el store
3. Redirija al dashboard

Esta página se llama **"callback handler"** y es parte estándar de cualquier integración OAuth2 en SPA.

---

## 5. TOTP y 2FA — Concepto

### ¿Qué es TOTP?

TOTP significa **Time-based One-Time Password** (Contraseña de un solo uso basada en tiempo). Es un estándar abierto definido en la [RFC 6238](https://datatracker.ietf.org/doc/html/rfc6238).

### ¿Cómo funciona?

TOTP usa **dos ingredientes** para generar un código:

```
Código TOTP = SHA1( secret + timestamp_de_30_segundos )
              │              │
              │              └── Unix timestamp dividido por 30
              │                  (cambia cada 30 segundos)
              │
              └── String secreto compartido entre
                  el backend y el autenticador
```

### El secreto compartido (shared secret)

Cuando un usuario activa 2FA:

```
Backend                                            Google Authenticator
──────                                            ────────────────────
1. Genera un string secreto:
   "JBSWY3DPEHPK3PXP"
   
2. Crea un "otpauth://" URI:
   otpauth://totp/Vyntra:email?secret=JBSWY3DPEHPK3PXP&issuer=Vyntra

3. Convierte el URI a QR ────────── QR ──────────►  4. Escanea el QR

5. Guarda el secret en la DB                     6. Guarda el secret
   (encriptado, con cifrado del usuario)            en el teléfono
   
7. Para verificar:                                8. Para generar código:
   - Toma el secret                                   - Toma el secret
   - Toma el tiempo actual (floor / 30)               - Toma el tiempo actual
   - Calcula SHA1(secret + time)                      - Calcula SHA1(secret + time)
   - Toma los últimos 6 dígitos                       - Toma los últimos 6 dígitos
   - Compara con lo que el usuario ingresó            - Muestra: 482951
```

### El "problema de los 30 segundos"

El código cambia cada 30 segundos. Esto significa:

- Si el usuario genera un código en el segundo 29 y lo envía en el segundo 31, el código ya cambió.
- La solución: **ventana de tolerancia**. El backend verifica el código actual, el anterior (hace 30 segundos) y el siguiente (dentro de 30 segundos). Esto da una ventana de ~90 segundos.

```php
// Concepto: verificar con ventana de ±1 paso
$valid = $google2fa->verifyKey($secret, $userCode, 1); // 1 = ventana de ±1
```

### Backup codes

Además del secret TOTP, cuando activas 2FA el backend genera **10 backup codes**. Son códigos de un solo uso, de 8-10 caracteres alfanuméricos:

```
BACKUP CODES — GUÁRDALOS EN UN LUGAR SEGURO
════════════════════════════════════════════
1.  XK7P-M9N2
2.  R4BT-W8K1
3.  P3LM-V6Q9
4.  H2NJ-F5R7
5.  D8GW-X3K4
6.  C1V5-B9N2
7.  T6M8-L4P7
8.  W9K3-J2H5
9.  F7R1-D6G4
10. N5B8-X2V1
```

**¿Para qué sirven?** Si el usuario pierde el teléfono, puede usar uno de estos códigos para desactivar 2FA y configurar uno nuevo. Cada backup code solo se puede usar una vez.

### ¿Por qué NO usamos SMS 2FA?

El NIST (National Institute of Standards and Technology) desaconseja el uso de SMS para 2FA porque:
- Los mensajes SMS pueden ser interceptados (SS7 attacks)
- El número de teléfono puede ser secuestrado (SIM swapping)
- El usuario puede no tener señal

**Alternativa que SÍ usamos:** TOTP vía Google Authenticator / Authy. Esto es lo que usa Discord y es el estándar de la industria.

---

## 6. Arquitectura General

```
┌──────────────────────────────────────────────────────────────────────────┐
│                          FRONTEND (Next.js 16 SPA)                       │
│                                                                          │
│  ┌─────────────────┐  ┌──────────────────┐  ┌──────────────────────┐    │
│  │ auth.service.js  │  │ useAuth.js       │  │ useAuthStore.js      │    │
│  │ (API calls)      │  │ (React Query)    │  │ (Zustand persist)    │    │
│  └────────┬────────┘  └────────┬─────────┘  └──────────┬───────────┘    │
│           │                    │                        │                │
│           └────────────────────┴────────────────────────┘                │
│                                │                                         │
│                     HTTP con Bearer Token                                │
│              Authorization: Bearer 1|abc123def456...                     │
└────────────────────────────────┬─────────────────────────────────────────┘
                                 │
                                 ▼
┌──────────────────────────────────────────────────────────────────────────┐
│                        BACKEND (Laravel 13 API)                          │
│                                                                          │
│  ┌──────────────────────────────────────────────────────────────────┐   │
│  │                    CAPA DE AUTENTICACIÓN                          │   │
│  │                                                                   │   │
│  │  Sanctum 4.0 ◄────► Socialite ◄────► 2FA TOTP ◄────► Email      │   │
│  │  (API tokens)       (OAuth2)          (custom)       Verif.       │   │
│  └──────────────────────────────────────────────────────────────────┘   │
│                                                                          │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌──────────────────┐  │
│  │  Route     │  │ FormRequest│  │ Controller  │  │ API Resource     │  │
│  │  (api.php) │─►│ (validar)  │─►│ (orquestar) │─►│ (formatear)      │─► JSON
│  └────────────┘  └────────────┘  └────────────┘  └──────────────────┘  │
│                                        │                                │
│                                        ▼                                │
│                                  ┌────────────┐                        │
│                                  │   Model    │                        │
│                                  │  (Eloquent) │─► PostgreSQL          │
│                                  └────────────┘                        │
│                                                                          │
│  ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────────┐  │
│  │  Redis Cache     │  │  PgBouncer       │  │  Octane (Swoole)     │  │
│  │  (tokens rápidos)│  │  (conexiones DB) │  │  (alto rendimiento)  │  │
│  └──────────────────┘  └──────────────────┘  └──────────────────────┘  │
└──────────────────────────────────────────────────────────────────────────┘
```

### Capas de una petición de autenticación

Cada capa tiene **UNA responsabilidad**. Esto es el Principio de Responsabilidad Única (SRP) que sigue todo el proyecto:

| Capa | Responsabilidad | Ejemplo |
|------|----------------|---------|
| **Route** | Definir URL + método HTTP + middleware | `Route::post('/auth/login', ...)` |
| **FormRequest** | Validar datos de entrada | `LoginRequest` verifica que email exista y sea válido |
| **Controller** | Orquestar: validar → ejecutar DB → responder | `AuthController@login` llama al modelo, crea token, devuelve JSON |
| **API Resource** | Formatear respuesta JSON | `UserResource` decide qué campos exponer del User |
| **Model** | Interactuar con la base de datos | `User::create()`, `$user->createToken()` |
| **Middleware** | Interceptar la petición (auth, rate limit) | `auth:sanctum` valida el token, `throttle:10,1` limita intentos |

> [!IMPORTANT]
> El Controller **NUNCA** debe tener `$request->validate()` inline. **NUNCA** debe formatear respuestas manualmente. **NUNCA** debe contener lógica de negocio compleja. Eso es trabajo del FormRequest, el Resource, y el Model, respectivamente.

---

## 7. ¿Por qué este stack es escalable a 100k usuarios?

### Sanctum es stateless por diseño

Cada servidor en un cluster de Octane puede validar tokens **independientemente**:

```
                    ┌─────────────┐
                    │  Load       │
                    │  Balancer   │
                    └──────┬──────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
           ▼               ▼               ▼
    ┌────────────┐  ┌────────────┐  ┌────────────┐
    │ Servidor 1 │  │ Servidor 2 │  │ Servidor 3 │
    │ (Octane)   │  │ (Octane)   │  │ (Octane)   │
    │            │  │            │  │            │
    │ Valida     │  │ Valida     │  │ Valida     │
    │ token      │  │ token      │  │ token      │
    │ contra DB  │  │ contra DB  │  │ contra DB  │
    └────────────┘  └────────────┘  └────────────┘
           │               │               │
           └───────────────┼───────────────┘
                           │
                           ▼
                   ┌──────────────┐
                   │  PostgreSQL   │
                   │  (o Redis)    │
                   └──────────────┘
```

**No hay sesiones compartidas** (excepto durante el callback de Socialite, que es puntual y breve). Cada servidor puede validar tokens por su cuenta, lo que significa que escalar es tan simple como añadir más servidores detrás del balanceador.

### Redis cache para tokens

Aunque Sanctum valida contra DB (consultando `personal_access_tokens`), podemos cachear los hashes de tokens válidos en Redis:

```
Petición llega → ¿Token en Redis? ──Sí──→ Usuario autenticado (sin DB)
                     │
                     No
                     │
                     ▼
              Consulta a PostgreSQL
                     │
                     ▼
              ¿Válido? ───Sí──→ Guardar en Redis (TTL) → Usuario autenticado
                     │
                     No
                     │
                     ▼
              401 Unauthorized
```

Esto reduce las consultas a DB para tokens reutilizados frecuentemente.

### 2FA solo añade overhead en login

La verificación 2FA ocurre **una vez por sesión** (al hacer login). No afecta el rendimiento de las peticiones normales:

```
Login (con 2FA):
  1. Validar credenciales → DB read
  2. Verificar 2FA activado → DB read
  3. Crear token pendiente → DB write
  4. Verificar código 2FA → cálculo TOTP (CPU, <1ms)
  5. Revocar token pendiente → DB write
  6. Crear token completo → DB write
  
  Total: 3 writes + 2 reads + 1ms CPU

Petición normal (después de login):
  1. Validar token → DB read (o Redis)
  2. Ejecutar lógica de negocio
  
  Total: 1 read
```

El 2FA **no añade overhead** a las peticiones normales. Solo impacta el login, que ya de por sí es un endpoint con rate limiting.

### Resumen de escalabilidad

| Factor | Impacto | Mitigación |
|--------|---------|------------|
| Validación de tokens contra DB | Lectura por petición | Redis cache reduce a 1 lectura cada N peticiones |
| Revocación de tokens | Escritura ocasional | Insignificante (logout, 2FA complete) |
| 2FA login | 3 writes extra | Rate limited a 10 intentos/minuto |
| Socialite callback | 1-2 DB writes | Ocurre una vez por usuario, no por petición |
| Sesiones compartidas | Solo en callback Socialite | Usa cookies, no Redis session store |

---

## 8. Comparación con Discord / Meta / X

| Feature | Discord | Meta (Facebook) | X (Twitter) | **Vyntra** |
|---------|---------|-----------------|-------------|-------------|
| Register (email+password) | ✅ | ✅ | ✅ | ✅ |
| Login (email+password) | ✅ | ✅ | ✅ | ✅ |
| Social login (Google) | ✅ | — | ✅ | ✅ |
| Social login (GitHub) | ❌ | ❌ | ❌ | ✅ (planeado) |
| Social login (Discord) | — | ❌ | ❌ | ✅ (planeado) |
| Social login (Apple) | ❌ | ❌ | ✅ | ❌ (prematuro) |
| Social login (Facebook) | ❌ | ✅ (Facebook Login) | ❌ | ❌ (prematuro) |
| Password reset (email) | ✅ | ✅ | ✅ | ✅ |
| Email verification | ✅ | ✅ | ✅ | ✅ |
| 2FA TOTP (Authenticator) | ✅ | ✅ | ✅ | ✅ |
| Backup codes (10) | ✅ | ✅ | ✅ | ✅ |
| Session management | ✅ | ✅ ("¿Dónde estás conectado?") | ✅ | ✅ |
| SMS 2FA | ❌ | ✅ | ✅ | ❌ (inseguro según NIST) |
| Passkeys / WebAuthn | ❌ | ✅ (security keys) | ❌ | ❌ (prematuro) |
| Hardware security key (FIDO2) | ❌ | ✅ | ❌ | ❌ (prematuro) |

### ¿Qué significa "prematuro"?

Vyntra sigue el principio **YAGNI** (You Ain't Gonna Need It): no implementamos nada que los usuarios no estén pidiendo hoy. Passkeys y FIDO2 son tecnologías valiosas, pero añaden complejidad significativa. Si los usuarios lo piden, se agrega después.

### Diferencias clave con Discord

| Aspecto | Discord | Vyntra |
|---------|---------|--------|
| **Backend** | Python + Rust | Laravel 13 + Octane |
| **Auth stack** | Session-based + API tokens | Sanctum API tokens |
| **Frontend** | React (desktop) + React Native (mobile) | Next.js 16 (SPA) |
| **OAuth providers** | Google | Google, GitHub, Discord |
| **SMS 2FA** | ❌ (no) | ❌ (no) |
| **Passkeys** | ❌ (no) | ❌ (prematuro) |

---

## 9. Lo que aprendiste en esta guía

### Checklist de conceptos

- [s ] **Sanctum**: Entiendo que es un sistema de autenticación por tokens Bearer, stateless, sin JWT, con hash en DB
- [s ] **Sanctum Dual**: Entiendo que tokens API son para la SPA y cookies de sesión solo para el callback de Socialite
- [s ] **Socialite**: Entiendo que es un cliente OAuth2 que abstrae el flocomplejo de login con terceros
- [s ] **google2fa**: Entiendo que implementa TOTP, un estándar de códigos de 6 dígitos que cambian cada 30 segundos
- [s ] **bacon-qr-code**: Entiendo que genera códigos QR para compartir el secret TOTP con el autenticador
- [s ] **Token Abilities**: Entiendo cómo se usan abilities de Sanctum para implementar 2FA sin sesiones (token `['2fa_pending']` → verificar → token `['*']`)
- [s ] **Por qué NO Fortify**: Entiendo que añade overhead, middlewares extra y está diseñado para Blade/sesiones, no para SPA+tokens
- [ s] **Por qué NO JWT**: Entiendo que Sanctum permite revocación instantánea (borrando de DB), JWT no se puede revocar hasta que expire
- [s ] **Flujo OAuth2**: Entiendo los pasos: redirect → Google auth → callback → code exchange → crear usuario → token Sanctum → redirect frontend con token
- [s ] **TOTP**: Entiendo que el secret se comparte via QR, que el código se calcula con secret + tiempo, y que hay backup codes por si se pierde el teléfono
- [s ] **SRP en auth**: Entiendo que Route define, FormRequest valida, Controller orquesta, Resource formatea, Model persiste
- [s ] **Escalabilidad**: Entiendo que Sanctum es stateless (cada servidor valida independientemente), que Redis cachea tokens, y que 2FA solo añade overhead en login
- [s ] **Comparativa con Discord**: Entiendo qué features compartimos y cuáles son prematuros (passkeys, FIDO2, SMS 2FA)

### Próximos pasos

Esta guía fue **conceptual**. Ahora que entiendes la teoría, la siguiente guía (`02_installation_and_configuration.md`) te enseñará a:

1. Configurar Sanctum correctamente (stateful domains, CORS, middlewares)
2. Verificar que el modelo User tenga los traits correctos
3. Configurar las variables de entorno para Socialite
4. Instalar y configurar google2fa + bacon-qr-code
5. Configurar rate limiting en rutas de auth
6. Primer test de humo: register → login → logout

---

> **Fin de la guía conceptual de autenticación.**  
> Creada para la rama `feature/auth` del proyecto Vyntra.  
> Esta guía NO contiene código de implementación — eso es responsabilidad de las guías siguientes (`02_*` en adelante).
