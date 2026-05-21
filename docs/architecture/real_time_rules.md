# 🚀 INSTRUCCIONES DE SISTEMA PARA LA IA: Manual de Arquitectura de Alta Escalabilidad (Vyne)

**Contexto para la IA:** Estás asistiendo en el desarrollo de "Vyne", una aplicación de mensajería en tiempo real diseñada para soportar escala masiva (similar a Discord). El stack tecnológico es: Next.js + React Query + Tailwind CSS (Frontend) y Laravel 13 (Update de 2026) + Reverb + PostgreSQL (Backend/BD).

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

Laravel Echo DEBE tener un listener global para el evento de reconexión. Al recuperar el internet/conexión, el frontend DEBE invalidar obligatoriamente la caché del canal actual (`queryClient.invalidateQueries`) para hacer un refetch en segundo plano y recuperar mensajes perdidos en el tiempo de desconexión.

### 6. Prohibido el `offset` (Paginación Infinita por Cursor)

El frontend SOLO puede usar `useInfiniteQuery` con **Cursor Pagination**. Prohibido usar el clásico `page=2` o `offset`. El frontend debe enviar el ID o fecha del último mensaje visible para que la BD filtre a partir de ahí.

---

## 🛑 REGLAS DE ORO DEL BACKEND (Laravel + PostgreSQL)

### 1. Columna UUID obligatoria

Toda tabla interactiva (`messages`, `notifications`) debe tener una columna `client_uuid` (string). Laravel debe guardar el UUID que le envía el frontend y **emitirlo de vuelta** en el Broadcast de Reverb.

### 2. Idempotencia a Nivel de Base de Datos (Protección anti-duplicados)

La columna `client_uuid` en PostgreSQL NO solo debe estar indexada, debe tener una restricción **`UNIQUE`**. Si ocurre un error o un doble clic, PostgreSQL debe bloquear físicamente el registro duplicado.

### 3. Broadcasting via Colas (Queues)

NUNCA hacer `broadcast()` de forma síncrona en el controlador. Siempre debe usar `ShouldBroadcast` implementando colas. El HTTP debe devolver `200 OK` rápido, y el WebSocket procesarse en un worker.

### 4. Payload Minimalista en WebSockets

Laravel solo debe emitir el `id`, la acción (`CREATED`, `UPDATED`) y los datos estrictamente nuevos. Prohibido emitir payloads gigantes. Si el frontend necesita más relaciones, React Query hará el refetch.

### 5. Índices Compuestos Estratégicos

PostgreSQL DEBE tener índices compuestos para consultas pesadas. (Ej: `$table->index(['channel_id', 'created_at']);`) para acelerar la paginación por cursor.

### 6. Throttling de Presencia ("Escribiendo...")

Los eventos tipo "Typing" DEBEN usar _Debounce/Throttle_. El frontend solo dispara 1 vez cada X segundos, y Laravel usa un _Rate Limiter_ de Redis para ignorar el spam.

---

## 🧑‍⚖️ DIRECTIVA DE COMPORTAMIENTO PARA LA IA (JURAMENTO DE ARQUITECTURA)

IA, a partir de este momento, estás sujeta a las siguientes reglas de interacción:

1. **Vigilancia Silenciosa:** Debes auditar todo el código que yo genere contra este manual. Si mi código es correcto y escalable, procede con normalidad sin recordarme las reglas.
2. **Aislamiento de Entorno:** Si estoy trabajando exclusivamente en una capa (ej. Frontend en Next.js), **NO me molestes ni me llenes de texto** recordando lo que tengo que hacer en Laravel o PostgreSQL. Mantén el foco 100% en el entorno actual.
3. **Alerta de Incompatibilidad (La Excepción):** SOLO puedes interrumpir y mencionar otra capa (ej. advertirme sobre Laravel mientras hago Next.js) **SI Y SOLO SI** la decisión de código que estoy tomando en este momento viola las reglas de este documento o generará un conflicto grave y directo de integración a futuro.
4. **Cero Tolerancia a Deuda Técnica:** Si detectas una mala práctica de escalabilidad (ej. intentar usar `useState` para datos del servidor), detén la generación, señala el error y aplica la solución arquitectónica correcta según este manual, directa y al grano, sin rodeos.
