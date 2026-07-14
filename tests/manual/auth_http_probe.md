# Auth HTTP Probe — Manual de Testing contra Servidor Real

> **Creado por:** @CreateTester
> **Ejecutado por:** @TesterRequests
> **Objetivo:** Probar el flujo completo de autenticación contra `http://localhost:8000`
> usando comandos curl/PowerShell manuales.
>
> **Requisito previo:** Servidor Laravel corriendo en `http://localhost:8000`
> (`php artisan serve`). Base de datos vacía o con datos de prueba.
> Tener `APP_URL=http://localhost:8000` y `APP_FRONTEND_URL=http://localhost:3000`
> en el `.env` del servidor.

---

## Formato de cada paso

```
## [PASO N]: [Nombre]

**Endpoint:** `[MÉTODO] [URL]`
**Comando:**
```
[comando curl/PowerShell]
```
**Validación esperada:**
- [qué debe responder la API]
**Detección de fallos:**
- [qué observar si algo sale mal]
```

---

## PASO 1: Register (Registro de usuario)

**Endpoint:** `POST /api/auth/register`

**Comando (PowerShell):**
```powershell
$body = @{
    username = "testuser"
    first_name = "Test"
    last_name = "User"
    email = "testuser@example.com"
    password = "password123"
    password_confirmation = "password123"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/register" `
    -Method Post `
    -Body $body `
    -ContentType "application/json"

$response | ConvertTo-Json -Depth 10
```

**Comando (curl):**
```bash
curl -s -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "first_name": "Test",
    "last_name": "User",
    "email": "testuser@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }' | jq .
```

**Validación esperada:**
- Status HTTP: `201 Created`
- Cuerpo JSON con estructura:
  ```json
  {
    "status": "success",
    "data": {
      "user": { "uuid": "...", "username": "testuser", ... },
      "token": "1|...",
      "token_type": "Bearer",
      "profile_completed": true
    }
  }
  ```
- El token debe empezar con `1|` (Sanctum plainTextToken)
- `user.email` debe ser `testuser@example.com`

**Detección de fallos:**
- Status 422 → error de validación (revisar campos requeridos)
- Status 500 → error de servidor (revisar logs de Laravel)
- El token no empieza con `1|` → posible cambio en formato Sanctum
- `profile_completed: false` → si el usuario fue creado con username null (raro en register normal)

**Guardar token:** Extraer el token de la respuesta para usarlo en pasos siguientes:
- PowerShell: `$token = $response.data.token`
- Bash: `TOKEN=$(curl -s ... | jq -r '.data.token')`

---

## PASO 2: Login sin 2FA

> Usar el usuario recién creado (NO tiene 2FA activado).

**Endpoint:** `POST /api/auth/login`

**Comando (PowerShell):**
```powershell
$body = @{
    email = "testuser@example.com"
    password = "password123"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/login" `
    -Method Post `
    -Body $body `
    -ContentType "application/json"

$response | ConvertTo-Json -Depth 10
```

**Comando (curl):**
```bash
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "password123"
  }' | jq .
```

**Validación esperada:**
- Status HTTP: `200 OK`
- Estructura:
  ```json
  {
    "status": "success",
    "data": {
      "user": { ... },
      "token": "2|...",
      "token_type": "Bearer",
      "profile_completed": true
    }
  }
  ```
- **NO** debe aparecer `requires_2fa: true`
- El token debe ser diferente al de register (nuevo token)

**Detección de fallos:**
- Status 422 → credenciales inválidas o error de validación
- Aparece `requires_2fa: true` → el usuario tiene 2FA activado inesperadamente
- Status 429 → rate limiting activo (documentar si aparece)

**Guardar token:** `$token = $response.data.token`

---

## PASO 3: GET /api/user (proteger endpoint con token)

> Verificar que el token completo permite acceder a datos del usuario.

**Endpoint:** `GET /api/user`

**Comando (PowerShell):**
```powershell
$headers = @{ Authorization = "Bearer $token" }
$response = Invoke-RestMethod -Uri "http://localhost:8000/api/user" `
    -Method Get `
    -Headers $headers

$response | ConvertTo-Json -Depth 10
```

**Comando (curl):**
```bash
curl -s http://localhost:8000/api/user \
  -H "Authorization: Bearer $TOKEN" | jq .
```

**Validación esperada:**
- Status HTTP: `200 OK`
- Devuelve datos del usuario autenticado
- El UUID del user debe coincidir con el del register/login

**Detección de fallos:**
- Status 401 → token no enviado o inválido
- Status 403 → token no tiene ability `*` (token pendiente de 2FA)
- Status 500 → error interno

---

## PASO 4: Habilitar 2FA (Enable → Confirm → Verify)

> Flujo completo de activación de 2FA en un usuario que NO lo tiene.

### 4a. Enable — Generar secret y QR

**Endpoint:** `POST /api/auth/2fa/enable`

**Comando (PowerShell):**
```powershell
$body = @{ password = "password123" } | ConvertTo-Json
$headers = @{ Authorization = "Bearer $token" }

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/2fa/enable" `
    -Method Post `
    -Headers $headers `
    -Body $body `
    -ContentType "application/json"

$response | ConvertTo-Json -Depth 10
$global:secret = $response.data.secret
Write-Host "2FA Secret: $secret"
```

**Comando (curl):**
```bash
SECRET=$(curl -s -X POST http://localhost:8000/api/auth/2fa/enable \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"password": "password123"}' | jq -r '.data.secret')

echo "2FA Secret: $SECRET"
```

**Validación esperada:**
- Status: `200 OK`
- Respuesta con: `data.secret`, `data.qr_code` (base64 SVG), `data.manual_url`

**Detección de fallos:**
- Status 422 → contraseña incorrecta
- Status 401/403 → token inválido
- `data.secret` vacío → error de generación
- Status 500 → error en Google2FA (puede faltar extensión PHP)

### 4b. Confirm — Verificar primer TOTP y obtener backup codes

> Necesitas generar un TOTP con el secret. Usa una app como Google Authenticator
> o genera el código manualmente.

**Comando (PowerShell):**
```powershell
# Requiere: tener $secret del paso anterior
# Generar TOTP de 6 dígitos (necesitas Google2FA o app externa)
# Para test automático podés usar: (Get-Date -UFormat %s) / 30 -> floor -> sha1 -> truncate
# Pero lo más fácil: usar una app 2FA en el celular escaneando el QR

# Alternativa manual: instalar el paquete bermuda7/totp si es necesario
# Por ahora, ingresá manualmente el código de 6 dígitos:
$totpCode = Read-Host "Ingresá el código TOTP de 6 dígitos (usá el secret en Google Authenticator)"

$body = @{ code = $totpCode } | ConvertTo-Json
$headers = @{ Authorization = "Bearer $token" }

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/2fa/confirm" `
    -Method Post `
    -Headers $headers `
    -Body $body `
    -ContentType "application/json"

$response | ConvertTo-Json -Depth 10
$global:backupCodes = $response.data.backup_codes
Write-Host "Backup codes: $backupCodes"
```

**Comando (curl):**
```bash
# Pedir código TOTP al usuario
read -p "Ingresa el código TOTP (usa el secret en tu app 2FA): " TOTP_CODE

BACKUP_CODES=$(curl -s -X POST http://localhost:8000/api/auth/2fa/confirm \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"code\": \"$TOTP_CODE\"}" | jq -r '.data.backup_codes[]')

echo "Backup codes: $BACKUP_CODES"
```

**Validación esperada:**
- Status: `200 OK`
- `data.backup_codes` debe ser un array de 10 strings de 8 caracteres alfanuméricos
- El usuario ahora tiene `two_factor_enabled = true` en DB

**Detección de fallos:**
- Status 422 → código TOTP inválido o expirado
- `backup_codes` vacío → error de generación
- Status 500 → error de Google2FA

### 4c. Login con 2FA pendiente

**Endpoint:** `POST /api/auth/login`

**Comando (PowerShell):**
```powershell
$body = @{
    email = "testuser@example.com"
    password = "password123"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/login" `
    -Method Post `
    -Body $body `
    -ContentType "application/json"

$response | ConvertTo-Json -Depth 10
$global:pendingToken = $response.data.token
```

**Comando (curl):**
```bash
PENDING_TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "password": "password123"
  }' | jq -r '.data.token')

echo "Pending token: $PENDING_TOKEN"
```

**Validación esperada:**
- Status: `200 OK`
- **Debe** incluir `requires_2fa: true`
- El token es diferente al anterior (token pendiente con ability `2fa_pending`)

**Detección de fallos:**
- No aparece `requires_2fa: true` → 2FA no se activó correctamente (revisar paso 4b)
- Error 422 → credenciales inválidas
- El token es igual al anterior → el endpoint no distingue 2FA

### 4d. Verificar 2FA con TOTP (obtener token completo)

**Endpoint:** `POST /api/auth/2fa/verify`

**Comando (PowerShell):**
```powershell
# Usar el pendingToken del paso 4c
$totpCode = Read-Host "Ingresá el código TOTP de 6 dígitos"

$body = @{ code = $totpCode } | ConvertTo-Json
$headers = @{ Authorization = "Bearer $pendingToken" }

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/2fa/verify" `
    -Method Post `
    -Headers $headers `
    -Body $body `
    -ContentType "application/json"

$response | ConvertTo-Json -Depth 10
$global:fullToken = $response.data.token
```

**Comando (curl):**
```bash
read -p "Ingresa el código TOTP: " TOTP_CODE

FULL_TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/2fa/verify \
  -H "Authorization: Bearer $PENDING_TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"code\": \"$TOTP_CODE\"}" | jq -r '.data.token')

echo "Full token: $FULL_TOKEN"
```

**Validación esperada:**
- Status: `200 OK`
- Devuelve un nuevo token con ability `['*']` (token completo)
- El pending token anterior queda revocado

**Detección de fallos:**
- Status 403 → el token usado no es `2fa_pending` (tiene ability `*` no `2fa_pending`)
- Status 422 → código TOTP inválido
- Status 401 → pending token ya fue revocado (si ya se usó antes)

---

## PASO 5: Verificar 2FA con Backup Code

> Alternativa al TOTP: usar un backup code del paso 4b.

### 5a. Login → pending token

```powershell
# Igual que paso 4c, obtener nuevo pending token
$body = @{
    email = "testuser@example.com"
    password = "password123"
} | ConvertTo-Json

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/login" `
    -Method Post `
    -Body $body `
    -ContentType "application/json"

$pendingToken2 = $response.data.token
```

### 5b. Verify con backup code

**Endpoint:** `POST /api/auth/2fa/verify`

**Comando (PowerShell):**
```powershell
# Usar el primer backup code del paso 4b
$firstBackup = $global:backupCodes[0]
Write-Host "Usando backup code: $firstBackup"

$body = @{ code = $firstBackup } | ConvertTo-Json
$headers = @{ Authorization = "Bearer $pendingToken2" }

$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/2fa/verify" `
    -Method Post `
    -Headers $headers `
    -Body $body `
    -ContentType "application/json"

$response | ConvertTo-Json -Depth 10
```

**Comando (curl):**
```bash
FIRST_BACKUP=$(echo "$BACKUP_CODES" | head -1)
FULL_TOKEN_BC=$(curl -s -X POST http://localhost:8000/api/auth/2fa/verify \
  -H "Authorization: Bearer $PENDING_TOKEN2" \
  -H "Content-Type: application/json" \
  -d "{\"code\": \"$FIRST_BACKUP\"}" | jq -r '.data.token')
```

**Validación esperada:**
- Status: `200 OK`
- Obtener token completo

### 5c. Reutilizar el mismo backup code → debe fallar

```powershell
# Login otra vez
$response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/login" `
    -Method Post `
    -Body @{ email = "testuser@example.com"; password = "password123" } | ConvertTo-Json `
    -ContentType "application/json"

$pendingToken3 = $response.data.token

# Intentar con el mismo backup code
try {
    $body = @{ code = $firstBackup } | ConvertTo-Json
    $headers = @{ Authorization = "Bearer $pendingToken3" }
    $response = Invoke-RestMethod -Uri "http://localhost:8000/api/auth/2fa/verify" `
        -Method Post `
        -Headers $headers `
        -Body $body `
        -ContentType "application/json"
    Write-Host "ERROR: Debería haber fallado con 422"
} catch {
    if ($_.Exception.Response.StatusCode -eq 422) {
        Write-Host "OK: Backup code reuse rechazado (422)"
    } else {
        Write-Host "Status inesperado: $($_.Exception.Response.StatusCode)"
    }
}
```

**Validación esperada:**
- Status: `422 Unprocessable Entity`
- Mensaje: "Código inválido o expirado."

**Detección de fallos:**
- Status 200 → el backup code NO se consumió (bug de seguridad: reusable)
- Status diferente a 422 → comportamiento inesperado

---

## PASO 6: Forgot Password (Solicitar reset)

### 6a. Email existente

**Endpoint:** `POST /api/auth/forgot-password`

**Comando:**
```bash
curl -s -X POST http://localhost:8000/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "testuser@example.com"}' | jq .
```

**Validación esperada:**
- Status: `200 OK`
- Mensaje: "Si el correo existe, recibirás un enlace de restablecimiento."
- Se envía un email (en local se guarda en `laravel.log` o `mailtrap`)

**Detección de fallos:**
- Status 422 → el email no existe (documenta vulnerabilidad de email enumeration)
- Status 500 → error de mail driver

### 6b. Email inexistente

```bash
curl -s -X POST http://localhost:8000/api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "noexiste@example.com"}' | jq .
```

**Validación esperada:**
- Status: **422** (por la regla `exists:users,email` en el FormRequest)
- Esto DOCUMENTA la vulnerabilidad de email enumeration

---

## PASO 7: Reset Password

> Para este paso necesitás el token de reset que Laravel genera.
> En local, podés obtenerlo de:
> - `storage/logs/laravel.log` (si usas log mail driver)
> - La tabla `password_reset_tokens` en la DB

### 7a. Reset válido

```bash
# Reemplazar con el token real de los logs o DB
RESET_TOKEN="token_obtenido_de_logs"

curl -s -X POST http://localhost:8000/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d "{
    \"email\": \"testuser@example.com\",
    \"token\": \"$RESET_TOKEN\",
    \"password\": \"newpassword456\",
    \"password_confirmation\": \"newpassword456\"
  }" | jq .
```

**Validación esperada:**
- Status: `200 OK`
- Devuelve `data.user` y `data.token` (nuevo token, auto-login)
- La contraseña anterior ya no funciona

**Detección de fallos:**
- Status 400 → token inválido o expirado
- Status 422 → error de validación (email que no existe, password corto, etc.)

### 7b. Reset con token inválido

```bash
curl -s -X POST http://localhost:8000/api/auth/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "testuser@example.com",
    "token": "token-invalido-que-no-existe",
    "password": "newpassword456",
    "password_confirmation": "newpassword456"
  }' | jq .
```

**Validación esperada:**
- Status: `400 Bad Request`

### 7c. Reset con email que no matchea

```bash
# Crear otro usuario primero
curl -s -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "userb",
    "first_name": "User",
    "last_name": "B",
    "email": "userb@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }' | jq .

# Obtener reset token para userb (de logs)
# Luego intentar resetear con email de testuser pero token de userb
```

**Validación esperada:**
- Status: `400 Bad Request`

---

## PASO 8: Email Verification

> Similar al reset, necesitás una signed URL.
> Laravel las genera y las envía por email.
> Podés construirlas manualmente también.

### 8a. Verificación con URL firmada válida

```bash
# Obtener datos del usuario
USER_UUID=$(curl -s http://localhost:8000/api/user \
  -H "Authorization: Bearer $TOKEN" | jq -r '.data.uuid')

# Construir signed URL (necesitás APP_KEY del servidor)
# En PHP: URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [...])
# Como alternativa, obtener de logs de email

# Si tenés acceso al servidor, ejecutar en Tinker:
# use App\Models\User; use Illuminate\Support\Facades\URL;
# $user = User::first();
# echo URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->uuid, 'hash' => sha1($user->email)]);
```

**Validación esperada:**
- Status: `200 OK`
- Mensaje: "Email verificado correctamente."

### 8b. Verificación con hash manipulado

> Modificar el hash en la URL firmada.

```bash
# Sobre la URL del paso 8a, cambiar el hash por uno inválido
# Ej: reemplazar el sha1 por "manipulated-hash"
```

**Validación esperada:**
- Status: `403 Forbidden`

### 8c. Verificación sin firma

```bash
USER_UUID="uuid_del_usuario"
EMAIL_HASH=$(echo -n "testuser@example.com" | shasum | cut -d' ' -f1)

curl -s "http://localhost:8000/api/auth/email/verify/$USER_UUID/$EMAIL_HASH" | jq .
```

**Validación esperada:**
- Status: `403 Forbidden` (porque la URL no tiene `?signature=...`)

---

## PASO 9: Flujo Socialite

> Socialite requiere configuración de OAuth en el `.env`.
> En local, podés observar el flujo de redirección.

### 9a. Redirect a proveedor

**Endpoint:** `GET /api/auth/social/{provider}`

```bash
# Probar con GitHub (requiere GITHUB_CLIENT_ID y GITHUB_CLIENT_SECRET en .env)
curl -s -v http://localhost:8000/api/auth/social/github 2>&1 | grep "location\|Location"
```

**Validación esperada:**
- Status: `302 Found` (redirección)
- `Location` header apunta a `https://github.com/login/oauth/authorize?...`

**Detección de fallos:**
- Status 500 → falta configuración de Socialite o provider no soportado
- No hay redirect → error de configuración

### 9b. Callback (observar token en URL)

> El callback de Socialite redirige al frontend con el token en la URL.
> En local, el frontend está en `http://localhost:3000`.

```bash
# Si simulás un callback exitoso, la redirección debe ser a:
# http://localhost:3000/auth/callback?token=1|...&profile_completed=false
# (o a /complete-profile si faltan datos)

# Podés observar el Location header:
curl -s -v "http://localhost:8000/api/auth/social/github/callback?code=test_code" 2>&1 | grep "location\|Location"
```

**Validación esperada:**
- Status: `302 Found`
- `Location` debe contener `frontend_url` y un `token` en query params
- Formato esperado: `http://localhost:3000/auth/callback?token=...&profile_completed=...`

**Detección de fallos:**
- Status 500 → error en Socialite provider (código inválido, etc.)
- Location no contiene `token` → el callback no está generando token
- Location no contiene `frontend_url` → configuración incorrecta

---

## Resumen de Vulnerabilidades Detectables

| # | Vulnerabilidad | Cómo detectarla |
|---|---------------|-----------------|
| 1 | **Email Enumeration** | Paso 6b: email inexistente → 422 (debería ser 200 para no revelar existencia) |
| 2 | **Brute Force Login** | Paso 2 repetido 11+ veces: nunca recibe 429 (no hay rate limiting) |
| 3 | **Backup Code Reuse** | Paso 5c: si el backup code reutilizado funciona (200), es un bug de seguridad |
| 4 | **Mass Assignment** | Pasos 1 y complete-profile: si `two_factor_secret` se persiste, es un bug grave |
| 5 | **Token Ability Bypass** | Paso 3 con pending token: si permite acceder a `/api/user`, es un bug de ability |

---

## Checklist para @TesterRequests

Antes de reportar resultados, verificar:

- [ ] ¿El servidor responde en `http://localhost:8000`?
- [ ] ¿La DB está migrada (`php artisan migrate`)?
- [ ] ¿Los códigos TOTP se generan correctamente (app 2FA o función manual)?
- [ ] ¿Los tokens Sanctum se guardan en `personal_access_tokens`?
- [ ] ¿Los emails se capturan en `storage/logs/laravel.log` (mail driver `log`)?
- [ ] ¿Socialite está configurado en `.env` (si se prueba ese flujo)?
