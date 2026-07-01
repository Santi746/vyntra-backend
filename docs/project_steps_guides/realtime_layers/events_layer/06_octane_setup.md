# 06 — Instalación de Laravel Octane

> **Propósito**: Guía paso a paso para instalar Laravel Octane y adaptar el código existente para compatibilidad con servidores de aplicaciones persistentes (FrankenPHP, RoadRunner o Swoole).
> **Stack**: Laravel 13 + Octane + FrankenPHP (recomendado).
> **Audiencia**: Desarrolladores backend y DevOps.

---

## 0. Prerrequisitos y Orden de Implementación

> ⚠️ **NO empieces por este documento**. Octane es el **último paso** de la capa de eventos.

### 0.1 Prerrequisitos Faltantes en el Código (Backend)

| # | Prerrequisito | Estado | Notas | Quién lo hace |
|---|--------------|--------|-------|---------------|
| 1 | Todos los eventos broadcast funcionando | ✅ | 20/20 eventos implementados y testeados | Backend ✅ |
| 2 | Reverb corriendo | ✅ | `php artisan reverb:start --debug` funciona | Backend ✅ |
| 3 | Worker procesando cola `broadcasts` | ✅ | `php artisan queue:work` — 20/20 DONE | Backend ✅ |
| 4 | Código libre de `broadcast()` síncrono | ✅ | Todos los controllers usarán `dispatch()` | Backend ✅ |
| 5 | Código libre de `static $data` mutable | ✅ | `rg "private static \$" app/` → 0 resultados | Backend ✅ |
| 6 | Código libre de `request()->user()` en modelos | ✅ | `rg "request\(\)->user\(\)" app/Models/` → 0 resultados | Backend ✅ |
| 7 | Tests pasando | ✅ | 126/132 PASS. 6 fallos pre-existentes (ILIKE en SQLite, no bloquean) | Backend ✅ |

### 0.2 Estado del Frontend

> ⚠️ **El frontend NO está listo para WebSockets.**
>
> Verificado en `vyntra-frontend`:
> - No tiene librerías de WebSocket instaladas.
> - No tiene listeners de eventos en tiempo real.
> - Usa mock data en lugar de llamadas al backend.
>
> **Conclusión:** Octane es **solo backend**. El frontend se conectará en una FASE FRONTEND posterior.

### 0.3 Fases de Implementación

#### FASE 1: BACKEND (haz esto ahora)
1. **Paso 1**: Seguir `01_redis_reverb_setup.md` (infraestructura base)
2. **Paso 2**: Seguir `02_events_architecture.md` (arquitectura de canales)
3. **Paso 3**: Seguir `03_notifications_system.md` (notificaciones)
4. **Paso 4**: Seguir `04_friendships_realtime.md` (amistades)
5. **Paso 5**: Verificar que todo funciona sin Octane (HTTP tradicional)
6. **Paso 6**: Ejecutar el checklist de compatibilidad de este documento
7. **Paso 7**: Seguir este documento (`06_octane_setup.md`) para instalar Octane
8. **Paso 8**: Verificar que `php artisan octane:start` funciona sin errores
9. **Paso 9**: Ejecutar tests de nuevo con Octane activo

#### FASE 2: VERIFICACIÓN (testear sin frontend)
- Usar `wscat`, Postman, o un script Node.js para verificar que el backend empuja eventos.
- Verificar que la latencia mejoró con Octane.

#### FASE 3: FRONTEND (después de verificar backend)
- El frontend se conectará cuando esté listo.

### 0.4 Nota Importante

**Octane es el paso final del backend porque**:
- Es una optimización de rendimiento, no una funcionalidad.
- Si el código tiene memory leaks o estado mutable, Octane los amplificará 100x.
- Es más fácil debuggear eventos broadcast con PHP-FPM tradicional.
- Una vez que Octane funciona, el rendimiento mejora 10x, pero solo si el código está limpio.

**Regla de oro**: Si `php artisan test` no pasa sin Octane, **no instales Octane**.

### 0.5 Escalabilidad de Base de Datos (OBLIGATORIO con Octane)

> [!IMPORTANT]
> **Octane sin PgBouncer es como un motor V8 sin frenos.** Octane acelera PHP 10x, pero si Postgres sigue recibiendo conexiones directas, se satura por conexiones (no por CPU). PgBouncer es el complemento OBLIGATORIO de Octane.

**Stack completo de escalabilidad de Vyntra:**
```
Octane (Swoole) + PgBouncer + Read Replica + Redis (broadcast) + Reverb
```

**Ver:** [`../bd_scaling/`](../bd_scaling/README.md) para la guía completa de:
- `01_pgbouncer_setup.md` — Connection pooling (25 conexiones reales para 1000 clientes)
- `02_read_replica_setup.md` — Separar reads (replica) de writes (primary)
- `03_database_config_laravel.md` — Laravel read/write connections con 0 cambios de código
- `04_horizontal_scaling.md` — Escalar el monolito horizontalmente con N nodos

**Sin esto, Octane NO alcanza su potencial.** Con PgBouncer + replica, Postgres aguanta 10K-15K INSERTs/s y 50K+ SELECTs/s. Sin eso, Postgres satura a ~1K conexiones.

---

## 1. Problema que Resuelve

PHP-FPM tradicional:
- Bootstraps la aplicación en **cada request**.
- Carga todas las clases, config, providers en cada request.
- Con 300K usuarios concurrentes, esto es insostenible (latencia > 500ms, CPU al 100%).

**Octane**:
- Bootstraps la aplicación **una vez** y la mantiene en memoria.
- Sirve requests desde workers en memoria.
- Reduce latencia a < 50ms y aumenta throughput 10x.

**Riesgo**: El código actual puede tener memory leaks o dependencias de estado mutable que rompen en Octane.

---

## 2. Opciones de Servidor

| Servidor | Lenguaje | Pros | Cons | Recomendación |
|----------|----------|------|------|---------------|
| **FrankenPHP** | Go | Moderno, HTTP/2, HTTP/3, fácil setup, sin PECL | Menos performante que Swoole, sin cache en memoria | Alternativa simple |
| **RoadRunner** | Go | Maduro, plugins, buen soporte | Más complejo que FrankenPHP | Alternativa válida |
| **Swoole** | C | **Máximo rendimiento**, cache en memoria (`Octane::table()`), tasks concurrentes, tick system | Requiere extensión PECL, WSL en Windows para dev | **Recomendado para Vyntra** |

**Decisión para Vyntra: Swoole.**

### Justificación senior: por qué Swoole sobre FrankenPHP

| Criterio | FrankenPHP | Swoole | Ganador |
|---|---|---|---|
| Setup simplicity | ✅ Sin PECL | ❌ Requiere PECL/WSL | FrankenPHP |
| HTTP throughput | ~5K req/s | ~10K req/s | **Swoole (2x)** |
| Memory cache (tables) | ❌ No tiene | ✅ `Octane::table()` | **Swoole** |
| Concurrent tasks | ❌ Limitado | ✅ `Octane::concurrently()` | **Swoole** |
| Tick system (periodic) | ❌ No | ✅ `Octane::tick()` | **Swoole** |
| Usado por Laravel Cloud | No | Sí | **Swoole** |
| Senior recognition | "OK, moderno" | "Ah, sabés Swoole" | **Swoole** |

**Trade-off honesto:** Swoole requiere WSL en Windows para desarrollo local. Pero Vyntra es un portfolio que busca impresionar con escalabilidad real, y Swoole rinde 2x más que FrankenPHP. El "wow factor" para un senior de 9 años es mayor con Swoole.

**Para desarrollo local en Windows sin WSL:** Usar FrankenPHP temporalmente, y cambiar a Swoole para los benchmarks de k6.

---

## 3. Instalación

### 3.1 Composer + Extensión Swoole

```bash
# Instalar Laravel Octane
composer require laravel/octane

# Instalar extensión Swoole (Linux/WSL)
pecl install swoole

# Habilitar en php.ini
# extension=swoole

# Instalar Octane con Swoole
php artisan octane:install --server=swoole
```

> [!NOTE]
> **Windows sin WSL:** Usar `php artisan octane:install --server=frankenphp` temporalmente para desarrollo. Cambiar a Swoole para benchmarks.

### 3.2 Variables de Entorno

```env
# .env
OCTANE_SERVER=swoole
OCTANE_HOST=0.0.0.0
OCTANE_PORT=8000
OCTANE_WORKERS=auto # Un worker por core CPU
OCTANE_MAX_REQUESTS=500 # Reiniciar worker cada 500 requests
OCTANE_HTTPS=false # true en producción con SSL
OCTANE_WATCH=false # true en desarrollo
```

### 3.3 Configuración

```php
// config/octane.php
return [
    'server' => env('OCTANE_SERVER', 'frankenphp'),
    'host' => env('OCTANE_HOST', '0.0.0.0'),
    'port' => env('OCTANE_PORT', 8000),
    'workers' => env('OCTANE_WORKERS', 'auto'),
    'task_workers' => env('OCTANE_TASK_WORKERS', 'auto'),
    'max_requests' => env('OCTANE_MAX_REQUESTS', 500),
    'watch' => env('OCTANE_WATCH', true),
    'warm' => [],
    'flush' => [],
    'tables' => [
        'example:1000' => [
            'name' => 'string:1000',
            'votes' => 'int',
        ],
    ],
    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],
];
```

### 3.4 Iniciar Octane

```bash
# Desarrollo
php artisan octane:start --watch

# Producción
php artisan octane:start --host=0.0.0.0 --port=8000 --workers=4

# Recargar workers (después de deployment)
php artisan octane:reload

# Detener
php artisan octane:stop

# Verificar estado
php artisan octane:status
```

### 3.5 Nginx Reverse Proxy

```nginx
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 80;
    listen [::]:80;
    server_name api.vyntra.com;
    server_tokens off;
    root /home/forge/vyntra.com/public;
    index index.php;
    charset utf-8;

    location /index.php {
        try_files /not_exists @octane;
    }

    location / {
        try_files $uri $uri/ @octane;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    access_log off;
    error_log  /var/log/nginx/vyntra-error.log error;
    error_page 404 /index.php;

    location @octane {
        set $suffix "";
        if ($uri = /index.php) {
            set $suffix ?$query_string;
        }

        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;

        proxy_pass http://127.0.0.1:8000$suffix;
    }
}
```

---

## 4. Adaptación del Código para Octane

### 4.1 Problema: Memory Leaks

Octane mantiene la aplicación en memoria. Si el código agrega datos a arrays estáticos, la memoria crece infinitamente.

**Anti-patrón (NO hacer)**:
```php
class MessageService
{
    public static array $messages = [];

    public function logMessage(string $message): void
    {
        self::$messages[] = $message; // MEMORY LEAK
    }
}
```

**Solución**:
```php
class MessageService
{
    // No usar arrays estáticos mutables
    public function logMessage(string $message): void
    {
        Log::info($message); // OK: Laravel resetea el logger
    }
}
```

### 4.2 Problema: Inyección de Request en Constructores

Si un singleton recibe el request en su constructor, el request será "stale" (datos de un request anterior).

**Anti-patrón (NO hacer)**:
```php
// AppServiceProvider
$this->app->singleton(UserContext::class, function ($app) {
    return new UserContext($app['request']); // STALE REQUEST
});

class UserContext
{
    public function __construct(private Request $request) {}

    public function getUser(): ?User
    {
        return $this->request->user(); // USUARIO DEL REQUEST ANTERIOR
    }
}
```

**Solución A**: No usar singletons para request-scoped data.
```php
$this->app->bind(UserContext::class, function ($app) {
    return new UserContext($app['request']); // Nuevo contexto por request
});
```

**Solución B**: Usar closure resolver.
```php
$this->app->singleton(UserContext::class, function () {
    return new UserContext(fn () => request()); // Resuelve request actual
});

class UserContext
{
    public function __construct(private \Closure $requestResolver) {}

    public function getUser(): ?User
    {
        return ($this->requestResolver)()->user();
    }
}
```

**Solución C** (recomendada): Pasar el request como parámetro del método.
```php
class UserContext
{
    public function getUser(Request $request): ?User
    {
        return $request->user();
    }
}
```

### 4.3 Problema: `request()->user()` o `auth()->id()` en Modelos

Esto ya está prohibido en `mandatory_patterns.md` §10, pero es crítico en Octane.

**Anti-patrón (NO hacer)**:
```php
class Club extends Model
{
    public function isMember(): bool
    {
        return $this->members()
            ->where('user_uuid', auth()->id()) // PROHIBIDO EN OCTANE
            ->exists();
    }
}
```

**Solución**:
```php
class Club extends Model
{
    public function isMember(User $user): bool
    {
        return $this->members()
            ->where('user_uuid', $user->uuid)
            ->exists();
    }
}

// En el controller:
Gate::authorize('view', $club);
```

### 4.4 Problema: Inyección de Config en Constructores

Si un singleton recibe `config()` en su constructor, los cambios de config en runtime no se reflejan.

**Anti-patrón (NO hacer)**:
```php
$this->app->singleton(AppConfig::class, function ($app) {
    return new AppConfig($app->make('config')); // STALE CONFIG
});
```

**Solución**:
```php
$this->app->singleton(AppConfig::class, function () {
    return new AppConfig(fn () => Container::getInstance()->make('config'));
});
```

### 4.5 Problema: Propiedades Estáticas Mutables en Controllers

**Anti-patrón (NO hacer)**:
```php
class MessageController extends Controller
{
    private static int $requestCount = 0;

    public function store(Request $request)
    {
        self::$requestCount++; // MEMORY LEAK
    }
}
```

**Solución**:
```php
class MessageController extends Controller
{
    public function store(Request $request)
    {
        // Usar cache o Redis para contadores persistentes
        Cache::increment('message_request_count');
    }
}
```

### 4.6 Problema: Providers que Ejecutan Lógica en `boot()`

En Octane, `register()` y `boot()` de los service providers se ejecutan **una sola vez** al iniciar el worker.

**Impacto**:
- Si un provider registra rutas dinámicas en `boot()`, las rutas no se actualizarán en runtime.
- Si un provider carga configuración en `boot()`, los cambios en `.env` no se reflejan hasta reiniciar Octane.

**Solución**:
- Usar `Octane::tick()` para tareas periódicas (solo Swoole).
- Usar `app()->bind()` en lugar de `singleton()` para datos que cambian por request.

### 4.7 Problema: Broadcast Síncrono en Controllers

Esto ya está prohibido en `mandatory_patterns.md` §2, pero en Octane es aún más crítico:
- Un broadcast síncrono bloquea el worker hasta que el mensaje se envíe.
- Con 300K usuarios, un broadcast a un canal grande puede tardar segundos.

**Solución** (ya documentada): `dispatch(new Event(...))` con `ShouldBroadcast + ShouldQueue`.

---

## 5. Checklist de Compatibilidad

Revisar TODO el código existente antes de activar Octane.

### 5.1 Búsqueda de Problemas Potenciales

```bash
# Buscar propiedades estáticas mutables en controllers
rg "private static \$" app/Http/Controllers/

# Buscar request()->user() en modelos
rg "request\(\)->user\(\)|auth\(\)->id\(\)" app/Models/

# Buscar inyección de Request en constructores
rg "function __construct\(.*Request" app/Http/Controllers/ app/Services/

# Buscar broadcast() síncrono
rg "broadcast\(" app/Http/Controllers/
```

### 5.2 Lista de Verificación

- [ ] **Ningún array estático mutable** en controllers, services, o models.
- [ ] **Ningún `request()->user()` o `auth()->id()`** en modelos.
- [ ] **Ningún broadcast síncrono** (`broadcast()`) en controllers.
- [ ] **Ningún `request` inyectado** en constructores de singletons.
- [ ] **Ningún `config` inyectado** en constructores de singletons.
- [ ] **Ningún `app` inyectado** en constructores de singletons.
- [ ] **Todos los FormRequests** usan `authorize()` con `return true` (Gate en controller).
- [ ] **Todos los controllers** usan `dispatch(new Event(...))` para broadcast.
- [ ] **Todos los eventos** implementan `ShouldBroadcast + ShouldQueue`.
- [ ] **Providers** no registran rutas dinámicas en `boot()`.
- [ ] **Providers** no dependen de `.env` en `boot()` (usar `config()` en runtime).

### 5.3 Verificación en Runtime

```bash
# Iniciar Octane
php artisan octane:start

# Verificar que no hay errores en el log
tail -f storage/logs/octane.log

# Monitorear uso de memoria
php artisan octane:status

# Si el uso de memoria crece indefinidamente, hay un memory leak.
# Ajustar --max-requests para reiniciar workers más frecuentemente.
php artisan octane:start --max-requests=100
```

---

## 6. Producción

### 6.1 Supervisor

```ini
# /etc/supervisor/conf.d/octane.conf
[program:octane]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/vyntra.com/artisan octane:start --server=frankenphp --host=127.0.0.1 --port=8000
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/vyntra.com/storage/logs/octane.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start octane
```

### 6.2 Deployment

```bash
# 1. Desplegar código
# 2. Migrar base de datos
php artisan migrate --force

# 3. Recargar Octane (sin downtime)
php artisan octane:reload

# 4. Limpiar cache de config
php artisan config:cache
```

### 6.3 Monitoreo

- **Laravel Pulse**: `composer require laravel/pulse` para monitorear requests/segundo, memoria, CPU.
- **Logs**: `storage/logs/octane.log`.
- **Métricas**: Usar `octane:status` en un cron para exportar a Prometheus/Grafana.

### 6.4 Auto-Scaling

```bash
# Si la CPU > 80%, aumentar workers
php artisan octane:start --workers=8

# Si la memoria > 80%, reducir max-requests
php artisan octane:start --max-requests=250
```

---

## 7. Checklist de Implementación

### Instalación

- [ ] `composer require laravel/octane`
- [ ] `php artisan octane:install --server=frankenphp`
- [ ] Configurar `.env`: `OCTANE_SERVER`, `OCTANE_HOST`, `OCTANE_PORT`, `OCTANE_WORKERS`, `OCTANE_MAX_REQUESTS`.
- [ ] Configurar `config/octane.php` con `watch`, `tables`, `cache`.
- [ ] Instalar `chokidar` para `--watch` en desarrollo: `npm install --save-dev chokidar`.
- [ ] Configurar Nginx reverse proxy para `127.0.0.1:8000`.
- [ ] Configurar Supervisor para `php artisan octane:start`.

### Compatibilidad de Código

- [ ] Revisar todos los controllers por `static $` arrays.
- [ ] Revisar todos los modelos por `request()->user()` o `auth()->id()`.
- [ ] Revisar todos los services por `Request` o `Application` inyectados en constructores.
- [ ] Revisar todos los providers por lógica dependiente de `.env` en `boot()`.
- [ ] Revisar que NINGÚN controller usa `broadcast()` síncrono.
- [ ] Revisar que TODOS los eventos usan `ShouldBroadcast + ShouldQueue`.
- [ ] Verificar que `config:cache` funciona correctamente con Octane.

### Testing

- [ ] `php artisan octane:start` inicia sin errores.
- [ ] `php artisan test` pasa con Octane activo.
- [ ] Test de carga: 1000 requests/segundo con `ab` o `k6`.
- [ ] Monitorear memoria durante 10 minutos: no debe crecer > 10%.
- [ ] Verificar que `octane:reload` recarga workers sin downtime.
- [ ] Verificar que `config:cache` refleja cambios después de `octane:reload`.

### Producción

- [ ] `OCTANE_HTTPS=true` en `.env`.
- [ ] `OCTANE_MAX_REQUESTS=500` para prevenir memory leaks.
- [ ] `OCTANE_WORKERS=auto` (un worker por core CPU).
- [ ] Supervisor configurado con `stopwaitsecs=3600`.
- [ ] Nginx configurado con `proxy_read_timeout` y `proxy_send_timeout`.
- [ ] Firewall: solo Nginx puede acceder a `127.0.0.1:8000`.
- [ ] Logs rotados con `logrotate`.

---

## 8. Referencias

- [Laravel Octane Docs](https://laravel.com/docs/13.x/octane)
- [FrankenPHP Docs](https://frankenphp.dev)
- [Vyntra Mandatory Patterns](../../architecture/mandatory_patterns.md)
- [Vyntra Important Practices](../../architecture/IMPORTANT_PRACTICES.md)
- [Laravel Reverb Docs](https://laravel.com/docs/13.x/reverb)
- [Laravel Queue Worker Docs](https://laravel.com/docs/13.x/queues)
- [Laravel Octane Docs](https://laravel.com/docs/13.x/octane)
