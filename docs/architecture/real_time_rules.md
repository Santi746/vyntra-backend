# 🚀 INSTRUCCIONES DE SISTEMA PARA LA IA: Manual de Arquitectura de Alta Escalabilidad (Vyne)

**Contexto para la IA:** Estás asistiendo en el desarrollo de "Vyne" (Vyntra), una aplicación de mensajería en tiempo real diseñada para soportar escala masiva (similar a Discord). El stack tecnológico es: Next.js + React Query + Tailwind CSS (Frontend) y Laravel 11/13 + Octane (Swoole) + Reverb + Redis + PostgreSQL (Backend/BD).

Tu trabajo como IA es auditar silenciosamente cada línea de código que yo (el usuario) escriba, asegurando que cumpla con las siguientes reglas **ESTRICTAS Y OBLIGATORIAS**.

---

## 🚨 El Problema de la "Bomba Atómica" (Race Conditions)

Cuando un usuario envía un mensaje de chat:

1. React Query actualiza la UI al instante (Optimistic Update).
2. El mensaje se envía por HTTP a Laravel.
3. Laravel guarda en PostgreSQL.
4. Laravel emite el evento vía Reverb a todos los usuarios en el canal (¡incluyendo al que lo envió!).
5. El cliente recibe el WebSocket.

**Riesgo:** Si no se gestiona bien, el usuario verá su propio mensaje duplicado, la caché de React Query se corromperá, y múltiples re-renders congelarán el navegador.

---

## 🛑 REGLAS DE ORO DEL FRONTEND (Next.js + React Query)

### 1. Prohibido usar `useState` para datos del servidor
Los mensajes, listas de usuarios o clubes **NUNCA** deben guardarse en un `useState`.
**Única fuente de la verdad:** La caché de React Query (`queryClient.setQueryData`).

### 2. El Patrón "UUID Cliente" (Deduplicación)
Todo mensaje o acción que dispare un evento en tiempo real debe nacer con un `UUID` único generado en el Frontend **ANTES** de tocar el Backend.
- El Frontend inyecta el mensaje optimista en la caché con ese `uuid` y estado `status: 'sending'`.
- Cuando Reverb avisa de un nuevo mensaje, el Frontend intercepta el ID. Si el ID ya existe en la caché, **NO LO DUPLICA**, solo le cambia el `status` a `'sent'`.

### 3. Anatomía Obligatoria de un `useMutation`
Cualquier mutación que interactúe con el backend DEBE implementar los 3 ciclos de vida:
- **`onMutate`**: Congela peticiones, hace backup de la caché anterior, inyecta el dato optimista.
- **`onError`**: Revierte la caché al backup instantáneamente si Laravel devuelve error.
- **`onSettled`**: Refresca silenciosamente en segundo plano para sincronizar la verdad absoluta.

### 4. Listeners Centralizados (Laravel Echo)
Los listeners de Reverb (`window.Echo.private(...)`) NO deben modificar la UI directamente. Su único trabajo es inyectar o modificar datos dentro de la caché de React Query.

### 5. El Protocolo "Túnel de Lavado" (Reconexión de WebSockets)
Laravel Echo DEBE tener un listener global para el evento de reconexión. Al recuperar la conexión, el frontend DEBE invalidar obligatoriamente la caché del canal actual (`queryClient.invalidateQueries`) para hacer un refetch en segundo plano y recuperar mensajes perdidos.

### 6. Prohibido el `offset` (Paginación Infinita por Cursor)
El frontend SOLO puede usar `useInfiniteQuery` con **Cursor Pagination**. Prohibido usar el clásico `page=2` o `offset`. El frontend debe enviar el ID o fecha del último mensaje visible para que la BD filtre a partir de ahí.

---

## 🛑 REGLAS DE ORO DEL BACKEND (Laravel + PostgreSQL)

### 1. Columna UUID obligatoria
Toda tabla interactiva (`messages`, `notifications`) debe tener una columna `client_uuid` (string). Laravel debe guardar el UUID que le envía el frontend y **emitirlo de vuelta** en el Broadcast de Reverb.

### 2. Idempotencia a Nivel de Base de Datos (Protección anti-duplicados)
La columna `client_uuid` en PostgreSQL NO solo debe estar indexada, debe tener una restricción **`UNIQUE`**. Si ocurre un error o un doble clic, PostgreSQL debe bloquear físicamente el registro duplicado.

### 3. Broadcasting via Colas (Queues)
NUNCA hacer `broadcast()` de forma síncrona en el controlador. Siempre debe usar `ShouldBroadcast` implementando colas. El HTTP debe devolver `200 OK` rápido, y el WebSocket procesarse en un worker en segundo plano (vía Redis/Database Queues).

### 4. Payloads Inteligentes en WebSockets (Evitar Auto-DDoS)
No todos los eventos de tiempo real se transmiten igual. Debes dividirlos en dos:
- **Eventos de Alta Velocidad (Chat / Mensajería):** Deben emitir el **payload completo** (el JSON idéntico al API Resource, ej: `MessageResource` con remitente anidado) para que el frontend pinte el mensaje al instante sin consultar al servidor. Emitir solo el ID obligaría a cientos de clientes a hacer peticiones HTTP simultáneas, tirando el servidor.
- **Eventos Estructurales Pesados (Clubes, Canales, Roles):** Deben emitir un **payload minimalista** (ej: `{ "club_uuid": "...", "action": "UPDATED" }`). El frontend invalidará silenciosamente su caché en segundo plano.

### 5. Índices Compuestos Obligatorios para Cursor
Para que la paginación por cursor en PostgreSQL no degrade el rendimiento al crecer las tablas:
- Toda tabla con paginación por cursor **DEBE** tener un índice compuesto que combine el filtro de contexto y la columna de ordenación (Ej: `$table->index(['club_channel_uuid', 'created_at']);`).

### 6. Throttling de Presencia ("Escribiendo...")
Los eventos tipo "Typing" DEBEN usar *Debounce/Throttle*. El frontend solo dispara 1 vez cada X segundos, y Laravel usa un *Rate Limiter* de Redis para ignorar el spam.

---

## 🛑 REGLAS DE COMPATIBILIDAD CON LARAVEL OCTANE + REDIS (EVITAR COLAPSOS EN MEMORIA)

### 1. Prohibido el Estado en Memoria (Stateful Singletons)
Dado que Laravel Octane mantiene la aplicación viva en memoria RAM entre peticiones HTTP:
- **PROHIBIDO** almacenar información específica de un usuario o de una petición en propiedades estáticas mutables o singletons que no se limpien.
- Todos los controladores, middleware y servicios deben ser completamente **stateless** (sin estado). Si necesitas guardar datos temporales durante el ciclo de vida de una petición, regístralos en el contenedor del `$request`.

### 2. Compatibilidad Nativa de Sesión/Tokens con Redis
Toda lógica de tokens y sesiones debe ser compatible de forma nativa con el driver de Redis de Sanctum y caché de sesión:
- Todos los tokens deben crearse utilizando la sintaxis estándar de Sanctum: `$user->createToken('auth_token')->plainTextToken`.
- Prohibido depender de almacenamiento en archivos locales (`file`) o bases de datos no distribuidas si impide mudar las sesiones de Sanctum y caché a Redis en un solo clic desde el archivo `.env`.

---

## 🧑‍⚖️ DIRECTIVA DE COMPORTAMIENTO PARA LA IA (JURAMENTO DE ARQUITECTURA)

IA, a partir de este momento, estás sujeta a las siguientes reglas de interacción:

1. **Vigilancia Silenciosa:** Debes auditar todo el código que yo genere contra este manual. Si mi código es correcto y escalable, procede con normalidad sin recordarme las reglas.
2. **Aislamiento de Entorno:** Si estoy trabajando exclusivamente en una capa (ej. Frontend en Next.js), **NO me molestes ni me llenes de texto** recordando lo que tengo que hacer en Laravel o PostgreSQL. Mantén el foco 100% en el entorno actual.
3. **Alerta de Incompatibilidad (La Excepción):** SOLO puedes interrumpir y mencionar otra capa o advertirme sobre problemas futuros (como memory leaks en Octane o índices faltantes en PostgreSQL) **SI Y SOLO SI** la decisión de código que estoy tomando en este momento viola las reglas de este documento o generará un conflicto de integración fatal a futuro.
4. **Cero Tolerancia a Deuda Técnica:** Si detectas una mala práctica de escalabilidad (ej. intentar usar `useState` para datos del servidor o variables estáticas con estado en controladores), detén la generación, señala el error y aplica la solución arquitectónica correcta según este manual, directa y al grano, sin rodeos.
