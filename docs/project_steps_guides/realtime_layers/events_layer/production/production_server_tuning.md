# Ajustes del Servidor — Producción

> 🖥️ **PRODUCCIÓN SOLO**. Esta guía extiende [`01_redis_reverb_setup.md`](../01_redis_reverb_setup.md).
>
> Contenido original de la sección **3.7** (Consideraciones de Producción).
> Solo aplica a despliegues en servidor real con múltiples conexiones WebSocket concurrentes.

---

## Open Files Limit

```bash
# Verificar límite actual
ulimit -n
# Default: 1024

# Aumentar para 10,000 conexiones
sudo nano /etc/security/limits.conf

# Agregar:
forge    soft    nofile    10000
forge    hard    nofile    10000

# Aplicar cambios
sudo sysctl -p
```

## Event Loop (ext-uv)

```bash
# Reverb usa stream_select por defecto (límite ~1,024 conexiones)
# Para > 1,000 conexiones por instancia:

pecl install uv
# Agregar 'extension=uv.so' a php.ini
```

## Nginx Worker Connections

```nginx
# /etc/nginx/nginx.conf
worker_rlimit_nofile 10000;

events {
    worker_connections 10000;
    multi_accept on;
}
```

## Supervisor para Reverb

```ini
# /etc/supervisor/conf.d/reverb.conf
[program:reverb]
process_name=%(program_name)s_%(process_num)02d
command=php /home/forge/vyntra.com/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=forge
redirect_stderr=true
stdout_logfile=/home/forge/vyntra.com/storage/logs/reverb.log
stopwaitsecs=3600

# Importante: minfds para Supervisor
[supervisord]
minfds=10000
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

---

## Referencias

- [Guía principal: 01_redis_reverb_setup.md](../01_redis_reverb_setup.md)
- [Supervisor Docs](http://supervisord.org/)
- [php-uv extension](https://github.com/amphp/ext-uv)
