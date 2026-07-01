# 07 — Horizon y Laravel Pulse (Observabilidad)

> **Propósito**: Guia de instalacion y configuracion de Laravel Horizon y Laravel Pulse para monitoreo de colas y rendimiento del backend.
> **Stack**: Laravel 13 + Horizon + Pulse + Redis.
> **Audiencia**: Desarrolladores backend y DevOps.
> **Cuando implementar**: AL FINAL, despues de que WebSocket (Reverb) este funcionando y probado.

---

## 0. Orden de Implementacion

> **IMPORTANTE**: No implementes este paso hasta que todo lo anterior este funcionando.

| # | Prerrequisito | Estado | Verificacion |
|---|--------------|--------|--------------|
| 1 | Redis instalado y funcionando | Debe estar desde `01_redis_reverb_setup.md` | `redis-cli ping` |
| 2 | Reverb corriendo sin errores | Debe estar desde `01_redis_reverb_setup.md` | `php artisan reverb:status` |
| 3 | Cola `broadcasts` procesando jobs sin fallos | Debe estar desde `02_events_architecture.md` | `php artisan queue:status` |
| 4 | Todos los eventos broadcast implementados y probados | Debe estar desde `02-04` | Checklist de `05_testing_backend_without_frontend.md` |
| 5 | Octane instalado (opcional, pero recomendado antes) | Preferible desde `06_octane_setup.md` | `php artisan octane:status` |

**Regla de oro**: Si `php artisan queue:status` muestra jobs fallidos, no instales Horizon hasta resolverlos. Horizon solo visualiza el problema, no lo soluciona.

---

## 1. Que es Horizon?

Laravel Horizon es un dashboard de colas en tiempo real que proporciona:

- **Metricas de throughput**: Cantidad de jobs procesados por minuto, hora, dia.
- **Jobs fallidos**: Lista detallada con stack traces, opcion para reintentar o borrar.
- **Workers activos**: Cuantos workers estan corriendo, en que cola, que jobs procesan.
- **Tiempo de procesamiento**: Cuanto tarda cada job en promedio (P50, P95, P99).
- **Cola de espera**: Jobs pendientes vs procesados, grafico de tendencia.
- **Balanceo de workers**: Asignacion automatica de workers segun la carga de cada cola.

**Por que lo necesitas en Vyntra**: Con la cola `broadcasts` procesando cientos de mensajes por minuto, necesitas visibilidad de:

- Cuantos jobs de broadcast estan en cola vs procesandose.
- Si hay cuellos de botella (jobs acumulandose).
- Que porcentaje de jobs fallan y por que.
- Si los workers estan saturados.

---

## 2. Que es Laravel Pulse?

Laravel Pulse es un panel de monitoreo de rendimiento del sistema que proporciona:

- **Slow queries**: Queries que superan un umbral de tiempo (configurable, default 1000ms).
- **Cache hits/misses**: Ratio de aciertos del cache Redis.
- **Endpoints lentos**: Rutas que mas tiempo de respuesta consumen.
- **Usuarios activos**: Conteo de usuarios autenticados activos (requiere `authenticated` recorder).
- **Uso de Redis**: Memoria, conexiones activas, hits vs misses.
- **Errores HTTP**: 404, 500 por endpoint.
- **Queue throughput**: Jobs procesados por segundo (complementario a Horizon).

**Por que lo necesitas en Vyntra**: Para detectar problemas de rendimiento antes de que afecten a usuarios:

- Queries N+1 en endpoints de clubs/mensajes.
- Endpoints de API que degradan la experiencia.
- Cache Redis saturado o mal configurado.

---

## 3. Instalacion

### 3.1 Horizon

```bash
composer require laravel/horizon

# Publicar configuracion y assets
php artisan horizon:install
```

Esto crea:

- `config/horizon.php` — Configuracion de workers, colas, balanceo.
- `app/Providers/HorizonServiceProvider.php` — Autorizacion del dashboard.

### 3.2 Pulse

```bash
composer require laravel/pulse

# Publicar configuracion y migraciones
php artisan pulse:install

# Ejecutar migraciones
php artisan migrate
```

Esto crea:

- `config/pulse.php` — Configuracion de recorders, umbrales, cache.
- Migraciones para tablas `pulse_entries`, `pulse_aggregates`, `pulse_values`.
- `app/Providers/PulseServiceProvider.php` — Autorizacion del dashboard.

---

## 4. Configuracion de Horizon

### 4.1 config/horizon.php

La configuracion clave para Vyntra:

```php
// config/horizon.php

'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['broadcasts', 'default'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'minProcesses' => 1,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],

    'local' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['broadcasts', 'default'],
            'balance' => 'simple',
            'processes' => 3,
            'tries' => 3,
            'timeout' => 60,
        ],
    ],
],
```

**Explicacion de opciones**:

| Opcion | Valor | Significado |
|--------|-------|-------------|
| `queue` | `['broadcasts', 'default']` | Workers escuchan primero `broadcasts`, luego `default`. |
| `balance` | `auto` | Horizon asigna workers segun la carga de cada cola. |
| `autoScalingStrategy` | `time` | Escala basado en tiempo de procesamiento (no en cantidad de jobs). |
| `minProcesses` / `maxProcesses` | 1 / 10 | Escala desde 1 worker hasta 10 segun demanda. |
| `tries` | 3 | Reintenta un job fallido hasta 3 veces antes de marcarlo como failed. |
| `timeout` | 60 | Mata un job que lleve mas de 60 segundos ejecutandose. |

### 4.2 Balanceo de Colas

Para Vyntra, la prioridad es:

1. **broadcasts** (alta prioridad): Mensajes en tiempo real, notificaciones. Deben procesarse en < 1s.
2. **default** (baja prioridad): Emails, procesamiento batch, tareas pesadas.

Horizon con `balance: auto` asignara automaticamente mas workers a `broadcasts` cuando haya acumulacion.

### 4.3 Tags para Horizon

Los tags permiten filtrar jobs por modelo o entidad en el dashboard:

```php
// En un Event que implementa ShouldQueue
public function tags(): array
{
    return [
        'broadcast',
        'message:' . $this->message->uuid,
        'channel:' . $this->message->channel_uuid,
    ];
}
```

Los tags recomendados para Vyntra:

| Tag | Ejemplo | Proposito |
|-----|---------|-----------|
| `broadcast` | `broadcast` | Filtrar todos los jobs de broadcast. |
| `message:{uuid}` | `message:abc-123` | Filtrar jobs de un mensaje especifico. |
| `channel:{uuid}` | `channel:def-456` | Filtrar jobs de un canal especifico. |
| `notification:{uuid}` | `notification:ghi-789` | Filtrar jobs de una notificacion. |
| `friendship:{uuid}` | `friendship:jkl-012` | Filtrar jobs de amistad. |

---

## 5. Configuracion de Pulse

### 5.1 config/pulse.php

```php
// config/pulse.php

'recorders' => [
    // Cache hits/misses
    CacheInteractions::class => [
        'enabled' => env('PULSE_CACHE_ENABLED', true),
    ],

    // Endpoints HTTP lentos
    SlowEndpoints::class => [
        'enabled' => true,
        'threshold' => env('PULSE_SLOW_ENDPOINT_THRESHOLD', 500), // ms
    ],

    // Queries lentas
    SlowQueries::class => [
        'enabled' => true,
        'threshold' => env('PULSE_SLOW_QUERY_THRESHOLD', 200), // ms
    ],

    // Requests por segundo
    Requests::class => [
        'enabled' => true,
    ],

    // Errores HTTP
    HttpErrors::class => [
        'enabled' => true,
    ],

    // Jobs de cola
    Queues::class => [
        'enabled' => true,
    ],

    // Usuarios autenticados activos
    AuthenticatedUsers::class => [
        'enabled' => env('PULSE_AUTHENTICATED_USERS_ENABLED', true),
    ],

    // Redis
    Redis::class => [
        'enabled' => env('PULSE_REDIS_ENABLED', true),
    ],
],

// Resolucion de usuarios para el dashboard
'users' => function (Illuminate\Http\Request $request) {
    return $request->user();
},
```

### 5.2 Recorders recomendados para Vyntra

| Recorder | Umbral | Que detecta |
|----------|--------|-------------|
| `SlowQueries` | 200ms | Queries sin indice, N+1, joins pesados. |
| `SlowEndpoints` | 500ms | Endpoints de API que degradan la experiencia. |
| `CacheInteractions` | N/A | Cache Redis mal configurado. |
| `Queues` | N/A | Jobs acumulados, complementa a Horizon. |
| `AuthenticatedUsers` | N/A | Usuarios activos concurrentes. |
| `Redis` | N/A | Memoria y conexiones Redis. |

### 5.3 Dashboard de Pulse

Una vez instalado, accede a `/pulse` en el navegador. El dashboard muestra:

- **Tarjeta de Redis**: Memoria usada, conexiones activas, hits/misses.
- **Tarjeta de Queries Lentas**: Lista de queries con duracion, frecuencia y query completa.
- **Tarjeta de Endpoints Lentos**: Rutas con mayor tiempo de respuesta.
- **Tarjeta de Errores HTTP**: 404, 500, 429 por ruta.
- **Tarjeta de Cola**: Jobs procesados por minuto.

---

## 6. Proteger las Rutas del Dashboard

Tanto Horizon como Pulse exponen dashboards internos. Deben protegerse con middleware de autenticacion y autorizacion.

### 6.1 Horizon Service Provider

```php
// app/Providers/HorizonServiceProvider.php

protected function gate(): void
{
    Gate::define('viewHorizon', function (User $user) {
        // Solo administradores del sistema
        return $user->email === 'admin@vyntra.com'
            || $user->hasClubPermission($user->clubs->first(), ClubPermission::ADMINISTRATOR);
    });
}
```

### 6.2 Pulse Service Provider

```php
// app/Providers/PulseServiceProvider.php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('viewPulse', function (User $user) {
        return $user->email === 'admin@vyntra.com'
            || $user->hasClubPermission($user->clubs->first(), ClubPermission::ADMINISTRATOR);
    });
}
```

### 6.3 Rutas Protegidas

Ambos dashboards se sirven automaticamente bajo `/horizon` y `/pulse`. Si necesitas personalizar las rutas:

```php
// En AppServiceProvider o RouteServiceProvider

// No es necesario para Horizon/Pulse 3.x+ (usan HorizonServiceProvider)
```

La proteccion via Gate es suficiente. Si deseas capa extra de middleware:

```bash
# Para entornos compartidos, puedes limitar por IP en Nginx
```

---

## 7. Uso Diario

### 7.1 Iniciar Horizon

```bash
# Desarrollo (procesa broadcasts + default)
php artisan horizon

# Produccion (gestionado por Supervisor)
sudo supervisorctl start horizon
```

### 7.2 Comandos Utiles

```bash
# Ver estado de Horizon
php artisan horizon:status

# Pausar Horizon (para mantenimiento)
php artisan horizon:pause

# Reanudar
php artisan horizon:continue

# Ver jobs fallidos
php artisan horizon:failed

# Reintentar un job fallido especifico
php artisan horizon:retry <id>

# Reintentar todos los jobs fallidos
php artisan horizon:retry all

# Limpiar jobs fallidos
php artisan horizon:forget <id>

# Obtener metricas (para scripts de monitoreo)
php artisan horizon:metrics
```

### 7.3 Supervisor para Horizon en Produccion

```ini
; /etc/supervisor/conf.d/horizon.conf
[program:horizon]
process_name=%(program_name)s
command=php /home/forge/vyntra.com/artisan horizon
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/vyntra.com/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start horizon
```

### 7.4 Comandos de Pulse

Pulse no necesita workers propios. Los datos se recopilan via middleware de Laravel y se almacenan en Redis.

Para limpiar datos antiguos:

```bash
# Opcional: limpiar entradas de Pulse viejas
php artisan pulse:clear
```

---

## 8. Interpretacion del Dashboard

### 8.1 Horizon Dashboard

Lo que debes monitorear diariamente:

| Metrica | Valor Saludable | Alarma |
|---------|-----------------|--------|
| Jobs pendientes en `broadcasts` | < 100 | > 1000 |
| Tiempo promedio de procesamiento | < 500ms | > 2s |
| Jobs fallidos (ultima hora) | 0 | > 5 |
| Workers activos | 2-4 | 0 o > 10 |
| Throughput | > 10 jobs/min | < 1 job/min |

### 8.2 Pulse Dashboard

| Metrica | Valor Saludable | Alarma |
|---------|-----------------|--------|
| Slow queries (> 200ms) | < 5/min | > 20/min |
| Cache hit ratio | > 80% | < 50% |
| Endpoints lentos (> 500ms) | < 3 | > 10 |
| Errores HTTP 5xx | 0 | > 1 |
| Conexiones Redis activas | < 50 | > 100 |

---

## 9. Checklist de Implementacion

### Instalacion

- [ ] `composer require laravel/horizon` ejecutado.
- [ ] `php artisan horizon:install` ejecutado.
- [ ] `composer require laravel/pulse` ejecutado.
- [ ] `php artisan pulse:install` ejecutado.
- [ ] `php artisan migrate` ejecutado (tablas de Pulse).

### Configuracion

- [ ] `config/horizon.php` configurado con cola `broadcasts` como prioridad.
- [ ] `config/horizon.php` con `balance: auto` y `autoScalingStrategy: time`.
- [ ] `config/pulse.php` con recorders habilitados: `SlowQueries`, `SlowEndpoints`, `Queues`, `Redis`.
- [ ] Umbrales ajustados: `SlowQueries` a 200ms, `SlowEndpoints` a 500ms.
- [ ] Tags agregados a eventos broadcast.

### Seguridad

- [ ] `HorizonServiceProvider::gate()` define permisos de acceso.
- [ ] `PulseServiceProvider::gate()` define permisos de acceso.
- [ ] Rutas `/horizon` y `/pulse` no son accesibles publicamente.

### Verificacion

- [ ] `php artisan horizon` inicia sin errores.
- [ ] `php artisan horizon:status` muestra workers activos.
- [ ] Dashboard de Horizon accesible en `/horizon`.
- [ ] Dashboard de Pulse accesible en `/pulse`.
- [ ] Datos de cola aparecen en Horizon tras procesar jobs.
- [ ] Metricas de rendimiento aparecen en Pulse tras algunas requests.

### Produccion

- [ ] Supervisor configurado para `artisan horizon`.
- [ ] Logs de Horizon rotados con logrotate.
- [ ] Acceso a dashboards limitado por IP (opcional, ademas del Gate).
- [ ] Alerta configurada si Horizon se detiene (monitor externo).

---

## 10. Referencias

- [Laravel Horizon Docs](https://laravel.com/docs/13.x/horizon)
- [Laravel Pulse Docs](https://laravel.com/docs/13.x/pulse)
- [Vyntra Mandatory Patterns](../../architecture/mandatory_patterns.md)
- [Vyntra Important Practices](../../architecture/IMPORTANT_PRACTICES.md)
- [Laravel Queue Docs](https://laravel.com/docs/13.x/queues)
- Documento previo: [`06_octane_setup.md`](06_octane_setup.md)
- Documento siguiente: [`08_testing_and_load_benchmarks.md`](08_testing_and_load_benchmarks.md)
