# 05 — Testing del Backend sin Frontend

> **Propósito**: Verificar que el backend de WebSockets funciona correctamente **sin** depender del frontend. Usar herramientas de línea de comandos y scripts simples.
> **Stack**: Laravel 13 + Reverb + Redis + wscat/Postman/script Node.js.
> **Audiencia**: Desarrolladores backend (especialmente principiantes en WebSockets).

---

## 1. Problema que Resuelve

Has implementado el backend de WebSockets (Reverb, eventos, canales, controllers), pero **no tienes frontend** para probarlo. ¿Cómo sabes si funciona?

**Solución**: Usar herramientas que simulen un cliente WebSocket para verificar que:
1. El servidor Reverb acepta conexiones.
2. La autenticación de canales privados funciona.
3. Los eventos llegan correctamente con el formato `{t, d}`.
4. El worker de colas procesa los jobs broadcast.

---

## 2. Herramientas de Testing

### 2.1 wscat (WebSocket Client en Terminal)

**Instalación**:
```bash
# Node.js
npm install -g wscat

# O si usas npx (sin instalar globalmente)
npx wscat
```

**Uso básico**:
```bash
# Conectar a un servidor WebSocket público
wscat -c ws://localhost:8080

# Conectar a un canal privado (necesita autenticación)
wscat -c ws://localhost:8080/app/my-app-key?protocol=7&client=js&version=8.4.0&flash=false
```

### 2.2 Postman (GUI)

Postman tiene soporte WebSocket:
1. Abre Postman.
2. Crea una nueva petición.
3. Selecciona la pestaña "WebSocket".
4. Introduce `ws://localhost:8080/app/my-app-key`.
5. Conecta y envía mensajes JSON.

### 2.3 Script de Node.js (Recomendado)

Crea un archivo `test-websocket.js` en el root del backend:

```javascript
const WebSocket = require('ws');

const WS_URL = 'ws://localhost:8080/app/3559175319fbab34ca59e2ac05bbc6a7';

const ws = new WebSocket(WS_URL);

ws.on('open', () => {
  console.log('✅ Conectado a Reverb');
  
  // Suscribirse a un canal privado (simulado)
  ws.send(JSON.stringify({
    event: 'pusher:subscribe',
    data: { channel: 'channel.test-uuid' }
  }));
});

ws.on('message', (data) => {
  const message = JSON.parse(data);
  console.log('📩 Mensaje recibido:', message);
  
  // Verificar formato {t, d}
  if (message.data && message.data.t && message.data.d) {
    console.log('✅ Formato {t, d} correcto');
    console.log('   t:', message.data.t);
    console.log('   d:', message.data.d);
  } else {
    console.log('❌ Formato incorrecto. Esperado: {t, d}');
  }
});

ws.on('error', (err) => {
  console.error('❌ Error:', err.message);
});

ws.on('close', () => {
  console.log('🔌 Conexión cerrada');
});

// Cerrar después de 30 segundos
setTimeout(() => ws.close(), 30000);
```

**Ejecutar**:
```bash
node test-websocket.js
```

---

## 3. Flujo de Testing End-to-End

### 3.1 Paso 1: Verificar que Reverb está corriendo

```bash
# En una terminal
php artisan reverb:start

# En otra terminal
wscat -c ws://localhost:8080/app/3559175319fbab34ca59e2ac05bbc6a7

# Si ves "connected", Reverb funciona.
```

### 3.2 Paso 2: Verificar la Cola

```bash
php artisan queue:work redis --queue=broadcasts --tries=1

# Si el worker procesa el job sin errores, la cola funciona.
```

### 3.3 Paso 3: Verificar Autenticación de Canales

```bash
# Usar curl para simular la petición de autorización de un canal privado

curl -X POST http://localhost:8000/broadcasting/auth \
  -H "Authorization: Bearer TU_TOKEN_SANCTUM" \
  -H "Content-Type: application/json" \
  -d '{"socket_id": "123.456", "channel_name": "private-user.tu-uuid"}'

# Si devuelve un JSON con "auth", la autenticación funciona.
```

### 3.4 Paso 4: Verificar Evento Completo

```bash
# Terminal 1: Escuchar WebSocket
wscat -c ws://localhost:8080/app/3559175319fbab34ca59e2ac05bbc6a7

# Terminal 2: Enviar un mensaje HTTP que dispare el evento

curl -X POST http://localhost:8000/api/channels/{channel_uuid}/messages \
  -H "Authorization: Bearer TU_TOKEN_SANCTUM" \
  -H "Content-Type: application/json" \
  -d '{"content": "Hola mundo", "client_uuid": "test-123"}'

# Terminal 1: Deberías ver llegar el evento con formato {t, d}
```

---

## 4. Checklist de Verificación Backend

- [ ] Reverb acepta conexiones WebSocket (`wscat` conecta).
- [ ] Worker procesa jobs de la cola `broadcasts` sin errores.
- [ ] Autenticación de canales privados funciona (`/broadcasting/auth` devuelve firma).
- [ ] Evento `MessageCreated` llega con formato `{t: 'MESSAGE_CREATE', d: {...}}`.
- [ ] Evento `NotificationCreated` llega con formato `{t: 'NOTIFICATION_CREATE', d: {...}}`.
- [ ] Evento `FriendshipStatusChanged` llega con formato `{t: 'FRIENDSHIP_STATUS_CHANGED', d: {...}}`.
- [ ] Los UUIDs en el payload son strings (no números).
- [ ] Las fechas están en ISO 8601.
- [ ] El evento incluye `client_uuid` para deduplicación.
- [ ] La conexión se mantiene abierta por más de 60 segundos (sin timeout prematuro).

---

## 5. Solución de Problemas Comunes

### 5.1 "Connection refused"

```bash
# Reverb no está corriendo
php artisan reverb:start

# O el puerto está ocupado
lsof -i :8080
```

### 5.2 "Unauthorized" en canales privados

```bash
# El token de Sanctum es inválido o expiró
# Verificar en routes/channels.php que la autorización es correcta
```

### 5.3 "Job failed" en el Worker

Puedes ver los jobs fallidos con:
```bash
php artisan queue:failed
```

Revisar los logs:

 tail -f storage/logs/laravel.log
```

> [!NOTE]
> **En Windows:** `redis-cli` no está disponible. Usar Docker:
> ```bash
> docker exec redis redis-cli LLEN queues:broadcasts
> docker exec redis redis-cli KEYS "*"
> ```

### 5.4 "Event not received" en WebSocket

```bash
# Verificar que el evento implementa ShouldBroadcast
# Verificar que broadcastOn() devuelve el canal correcto
# Verificar que el job está en la cola broadcasts
```

---

## 6. Próximo Paso

Una vez que todo el checklist esté verificado:
1. Pasa a `06_octane_setup.md` para optimizar el rendimiento.
2. **Después**, cuando el frontend esté listo, conecta el frontend a este backend.

---

> **Consejo del profesor**: No te preocupes si no tienes frontend. Si el backend empuja eventos correctamente y `wscat` los recibe, tu trabajo está hecho. El frontend es solo "pintura" encima de una estructura sólida. Asegúrate de que la estructura sea sólida primero.
