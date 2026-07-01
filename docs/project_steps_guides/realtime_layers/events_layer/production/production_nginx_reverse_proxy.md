# Nginx Reverse Proxy + SSL — Producción

> 🖥️ **PRODUCCIÓN SOLO**. Esta guía extiende [`01_redis_reverb_setup.md`](../01_redis_reverb_setup.md).
>
> Contenido original de las secciones **3.5** y **3.6** (Nginx Reverse Proxy y SSL).
> Solo aplica a despliegues en servidor real con dominio y certificado SSL.

---

## 3.5 Nginx Reverse Proxy

```nginx
# /etc/nginx/sites-available/vyntra
map $http_upgrade $connection_upgrade {
    default upgrade;
    ''      close;
}

server {
    listen 80;
    listen [::]:80;
    server_name ws.vyntra.com;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header SERVER_PORT $server_port;
        proxy_set_header REMOTE_ADDR $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";

        proxy_pass http://0.0.0.0:8080;

        # Timeouts para WebSocket
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
    }
}
```

## 3.6 SSL

```nginx
# Con Let's Encrypt
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name ws.vyntra.com;

    ssl_certificate /etc/letsencrypt/live/ws.vyntra.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ws.vyntra.com/privkey.pem;

    location / {
        proxy_pass http://0.0.0.0:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

---

## Referencias

- [Guía principal: 01_redis_reverb_setup.md](../01_redis_reverb_setup.md)
- [Laravel Reverb Docs — Production](https://laravel.com/docs/13.x/reverb#production)
