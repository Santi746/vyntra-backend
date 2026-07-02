# Running the App — Guía práctica (01/07/26)

## Dos formas de correr la app

### 1. Local con Herd (desarrollo diario)

Usa Herd con PHP 8.4. Puerto 80 en `http://vyntra-base.test`.

**Servicios necesarios en tu PC:**
| Servicio | Puerto | ¿Cómo saber si corre? |
|---|---|---|
| PostgreSQL | 5432 (DBngin) | Debe estar iniciado en Windows (DBngin) |
| Redis | 6379 | Debe estar iniciado en Windows |
| Reverb (WebSocket) | 8080 | `php artisan reverb:start` |

**Para arrancar:**
1. Abre PowerShell en la carpeta del proyecto
2. `php artisan serve` (o simplemente accede a `vyntra-base.test` si Herd ya corre)
3. `php artisan reverb:start` (solo si pruebas WebSockets)

**Puertos usados:** 80 (Herd), 8080 (Reverb), 5432 (PostgreSQL), 6379 (Redis)

---

### 2. Docker con Swoole (rendimiento)

Usa Docker Desktop. El contenedor `vyntra-backend-octane` corre PHP 8.4 + Swoole. Puerto 8000 en `http://localhost:8000`.

**Servicios dentro de Docker:**
| Servicio | Container | Puerto host |
|---|---|---|
| PostgreSQL | `vyntra-postgres` (dentro de Docker) | `5444` (solo debug) |
| Redis | Sigue en Windows (`host.docker.internal:6379`) | — |
| Reverb | Sigue en Windows (`host.docker.internal:8080`) | — |

**pgBouncer** (`vyntra-pgbouncer`) se sienta entre Octane y PostgreSQL. Octane nunca toca Postgres directo.

**Comandos Docker que usamos:**
| Comando | Qué hace |
|---|---|
| `docker compose up -d` | Enciende el contenedor en segundo plano |
| `docker compose down` | Apaga y elimina el contenedor |
| `docker compose build` | Reconstruye la imagen (tras cambios en Dockerfile) |
| `docker compose up -d --build` | Reconstruye y enciende (todo en uno) |
| `docker compose logs` | Muestra los logs del contenedor |
| `docker compose ps` | Muestra estado (corriendo/apagado) |
| `docker exec -it vyntra-backend-octane-1 bash` | Entra al contenedor (como SSH) |

**Siempre corre en segundo plano:** el contenedor `redis` (puerto 6379, imagen `redis:7`). No es parte de nuestro compose, está aparte. La base de datos PostgreSQL ahora también corre dentro de Docker como parte del compose.

---

## ¿Qué construimos?

| Componente | Archivo | Propósito |
|---|---|---|
| Imagen Docker | `Dockerfile` | Receta con PHP 8.4 + Swoole + Redis + PostgreSQL |
| Orquestador | `compose.yaml` | Define puerto (8000), variables de entorno, entrypoint |
| Filtro de build | `.dockerignore` | Evita copiar `vendor/`, `.git/`, etc. a la imagen |

**La imagen pesa ~1 GB** porque compila Swoole desde código C++ (603 archivos). Por eso el build tarda ~6 minutos.

---

## URLs de la app

| Entorno | URL |
|---|---|
| Herd (local) | `http://vyntra-base.test` |
| Docker (Swoole) | `http://localhost:8000` |
| Reverb WebSocket | `ws://vyntra-base.test:8080` (o `localhost:8080`) |

Ambas URLs sirven la MISMA app. Cambia solo el servidor (PHP built-in vs Swoole).

---

## Resolviendo problemas comunes

| Problema | Causa probable | Solución |
|---|---|---|
| `localhost:8000` no responde | Contenedor apagado | `docker compose up -d` |
| Puerto 8000 ocupado | Otro programa usa el puerto | Cerrarlo o cambiar puerto en `compose.yaml` |
| Docker build tarda ~6 min | Swoole se compila desde C++ | Es normal. Futuros builds usan caché |
| No veo el contenedor en Docker Desktop | Bug de interfaz tras recreate | `docker compose down; docker compose up -d` |
| curl da "Empty reply" | Falta `-H "Connection: close"` | Usar navegador o `curl -H "Connection: close"` |

---

## Recordatorio importante

- **No toques** `Dockerfile` ni `compose.yaml` si no sabes qué estás haciendo
- **Herd sigue siendo** tu herramienta principal para desarrollo
- **Docker es solo** para probar el servidor Swoole (alto rendimiento)
- **Siempre apaga** Docker (`docker compose down`) cuando termines de usarlo para liberar recursos
