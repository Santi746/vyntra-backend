# Monitoreo con Pulse — Opcional (valor portfolio)

> 🔧 **OPCIONAL — valor para portfolio**. Esta guía extiende [`01_redis_reverb_setup.md`](../01_redis_reverb_setup.md).
>
> Contenido original de la sección **3.9** (Monitoreo con Pulse).
> Agrega dashboards de monitoreo en tiempo real para Reverb (conexiones activas, mensajes procesados).

---

## 3.9 Monitoreo con Pulse

```bash
# Instalar Pulse
composer require laravel/pulse
php artisan pulse:install
```

```php
// config/pulse.php
'recorders' => [
    \Laravel\Reverb\Pulse\Recorders\ReverbConnections::class => [
        'sample_rate' => 1,
    ],
    \Laravel\Reverb\Pulse\Recorders\ReverbMessages::class => [
        'sample_rate' => 1,
    ],
],
```

```bash
# Ejecutar el daemon de check en el servidor Reverb
php artisan pulse:check
```

### Dashboard

Pulse provee un dashboard web en `/pulse` que muestra:

- **Conexiones activas** de Reverb en tiempo real.
- **Mensajes** broadcast enviados por segundo.
- **Jobs en cola** y su estado.
- **Uso de Redis** (memoria, hits, misses).

### Supervisor para Pulse Check

```ini
# /etc/supervisor/conf.d/pulse-check.conf (PRODUCCIÓN)
[program:pulse-check]
process_name=%(program_name)s
command=php /home/forge/vyntra.com/artisan pulse:check
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/vyntra.com/storage/logs/pulse-check.log
```

---

## Referencias

- [Guía principal: 01_redis_reverb_setup.md](../01_redis_reverb_setup.md)
- [Laravel Pulse Docs](https://laravel.com/docs/13.x/pulse)
- [Laravel Reverb — Pulse Integration](https://laravel.com/docs/13.x/reverb#pulse)
