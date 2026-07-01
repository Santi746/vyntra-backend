# Redis Cloud / Managed — Producción

> 🖥️ **PRODUCCIÓN SOLO**. Esta guía extiende [`01_redis_reverb_setup.md`](../01_redis_reverb_setup.md).
>
> Contenido original de la sección **2.2 Opción C** (Redis Cloud / Managed).
> Solo aplica a despliegues en servidor real donde no se ejecuta Redis localmente.

---

## Opción C: Redis Cloud / Managed

Usar Redis Cloud, AWS ElastiCache, o DigitalOcean Managed Redis.

```env
# .env
REDIS_HOST=redis.example.com
REDIS_PASSWORD=secret_password
REDIS_PORT=6379
REDIS_DB=0
```

### Proveedores Recomendados

| Proveedor | Producto | Ideal para |
|-----------|----------|------------|
| [Redis Cloud](https://redis.com/redis-enterprise-cloud/) | Redis Enterprise Cloud | Escalado automático, alta disponibilidad |
| [AWS ElastiCache](https://aws.amazon.com/elasticache/) | ElastiCache for Redis | Entornos AWS existentes |
| [DigitalOcean](https://www.digitalocean.com/products/managed-databases-redis/) | Managed Redis | Simplicidad, precio fijo |
| [Upstash](https://upstash.com/) | Serverless Redis | Proyectos serverless, precio por uso |

### Variables de Entorno Requeridas

```env
REDIS_HOST=<hostname del servicio>
REDIS_PASSWORD=<contraseña o token>
REDIS_PORT=6379
REDIS_DB=0

# Opcional: usar bases separadas para evitar conflictos
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2
```

### Verificación

```bash
redis-cli -h <host> -p 6379 -a <password> ping
# Respuesta esperada: PONG
```

---

## Referencias

- [Guía principal: 01_redis_reverb_setup.md](../01_redis_reverb_setup.md)
- [Laravel Redis Docs](https://laravel.com/docs/13.x/redis)
