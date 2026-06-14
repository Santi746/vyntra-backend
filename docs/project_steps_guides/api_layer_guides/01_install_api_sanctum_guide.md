# ⚙️ Guía: Instalación de API + Sanctum (Autenticación) ✅

> **Completado.** API habilitada, Sanctum configurado, migraciones de tokens activas.

**Objetivo:** Habilitar el soporte de rutas API en Laravel y configurar Sanctum como sistema de autenticación por tokens para el frontend (Next.js).

Este es el **paso 0** antes de crear cualquier controlador o ruta. Sin esto, Laravel no sabrá cómo manejar peticiones REST ni cómo protegerlas.

---

## ¿Por qué este paso existe?

Laravel 11+ ya no incluye `routes/api.php` por defecto para mantener el framework ligero. Tampoco incluye un sistema de autenticación listo. Este comando instala ambas cosas en un solo paso de manera oficial.

---

## Checklist

### 1. Instalar el soporte de API y Sanctum
- [ ] Ejecutar el comando oficial en la terminal:
  ```bash
  php artisan install:api
  ```
  Este comando realiza automáticamente:
  - Crea el archivo `routes/api.php`
  - Instala el paquete `laravel/sanctum`
  - Publica la migración de `personal_access_tokens`
  - Registra el middleware `auth:sanctum` globalmente

- [ ] Ejecutar las migraciones nuevas que Sanctum necesita:
  ```bash
  php artisan migrate
  ```
  *(Crea la tabla `personal_access_tokens` en PostgreSQL)*

---

### 2. Configurar el Modelo User para Sanctum
- [ ] Abrir `app/Models/User.php` y verificar que importa y usa el trait `HasApiTokens`:
  ```php
  use Laravel\Sanctum\HasApiTokens;

  class User extends Authenticatable
  {
      use HasApiTokens, HasUuids, SoftDeletes, HasFactory, Notifiable;
  ```
  > **¿Para qué?** Este trait le da al modelo User los métodos `createToken()`, `tokens()` y `currentAccessToken()` que usaremos en el controlador de autenticación para emitir y revocar tokens JWT.

---

### 3. Verificar la estructura de rutas creada
- [ ] Confirmar que existe el archivo `routes/api.php`
- [ ] Verificar que `bootstrap/app.php` registra las rutas API correctamente (Laravel 11+ lo hace automáticamente con `install:api`)

---

### 4. Verificar configuración de CORS (Cross-Origin)
- [ ] Revisar el archivo `config/cors.php` y asegurarse que el dominio del frontend esté permitido:
  ```php
  'allowed_origins' => ['http://localhost:3000'], // URL del frontend Next.js en local
  ```
  > **¿Para qué?** Sin esto, el navegador bloqueará todas las peticiones del frontend al backend por la política de seguridad CORS.

---

## Resultado esperado al finalizar

```
✅ routes/api.php existe y está registrado
✅ Tabla personal_access_tokens creada en PostgreSQL
✅ Modelo User tiene el trait HasApiTokens
✅ CORS configurado para localhost:3000
```

---

## Siguiente paso
➡️ [02_form_requests_guide.md](./02_form_requests_guide.md)
