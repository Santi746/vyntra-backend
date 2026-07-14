# 🔄 Flujo de Datos del Backend de Vyntra (Laravel)

Este documento explica visualmente cómo viaja una petición desde que el frontend (Next.js) la envía hasta que recibe una respuesta JSON del backend (Laravel).

---

## 1. El Viaje Completo de una Petición (Vista General)

Imagina que un usuario en el frontend presiona el botón **"Enviar Mensaje"** en un canal de chat. Esto es lo que ocurre por detrás:

![Flujo completo de una petición HTTP en Laravel](./assets/flujo_peticion.png)

---

## 2. Analogía del Restaurante 🍽️

Para que nunca olvides el propósito de cada capa, piensa en tu backend como un **restaurante de lujo**:

![Analogía del Restaurante: cada capa de Laravel es un rol en el restaurante](./assets/analogia_restaurante.png)

---

## 3. Los Dos Caminos: Lectura vs. Escritura

### 📖 Camino de LECTURA (GET) — "Quiero ver los mensajes del canal"

```mermaid
sequenceDiagram
    participant FE as 🖥️ Frontend
    participant RT as 🚪 Ruta
    participant MW as 🛡️ Middleware
    participant PO as 🔑 Policy
    participant CT as 🧠 Controller
    participant MD as 📦 Model
    participant DB as 🗄️ PostgreSQL
    participant RS as 🎨 API Resource

    FE->>RT: GET /api/channels/abc-123/messages
    RT->>MW: ¿Está autenticado?
    MW->>PO: ✅ Sí. ¿Tiene permiso para ver este canal?
    PO->>CT: ✅ Sí, pasa al Controller
    CT->>MD: "Dame los mensajes del canal abc-123"
    MD->>DB: SELECT * FROM channel_messages WHERE club_channel_uuid = 'abc-123'
    DB-->>MD: [fila1, fila2, fila3, ...]
    MD-->>CT: Colección de objetos ChannelMessage
    CT->>RS: "Formatea estos mensajes para el frontend"
    RS-->>FE: JSON: { data: [{uuid, content, sender, ...}, ...], meta: {next_cursor, per_page} }
```

> [!NOTE]
> En una lectura (GET), el **Form Request NO participa** porque no hay datos del usuario que validar. Solo se consulta información existente. Sin embargo, la **Policy SÍ participa** porque incluso para leer datos necesitas verificar que el usuario tenga permiso para ver ese recurso.

---

### ✏️ Camino de ESCRITURA (POST) — "Quiero enviar un mensaje nuevo"

```mermaid
sequenceDiagram
    participant FE as 🖥️ Frontend
    participant RT as 🚪 Ruta
    participant MW as 🛡️ Middleware
    participant PO as 🔑 Policy
    participant FR as 📋 Form Request
    participant CT as 🧠 Controller
    participant MD as 📦 Model
    participant DB as 🗄️ PostgreSQL
    participant RS as 🎨 API Resource

    FE->>RT: POST /api/channels/abc-123/messages {content: "Hola!", client_uuid: "xyz"}
    RT->>MW: ¿Está autenticado?
    MW->>PO: ✅ Sí. ¿Tiene permiso para enviar en este canal?
    
    alt Sin permiso
        PO-->>FE: ❌ 403: {status: "error", message: "No eres miembro de este club."}
    end
    
    PO->>FR: ✅ Sí. Ahora valida los datos del body.
    
    alt Datos inválidos
        FR-->>FE: ❌ 422: {errors: {content: ["El contenido es obligatorio"]}}
    end
    
    FR->>CT: ✅ Datos válidos y limpios
    CT->>MD: "Crea un mensaje nuevo con estos datos"
    MD->>DB: INSERT INTO channel_messages (content, sender_uuid, ...) VALUES ('Hola!', '...', ...)
    DB-->>MD: Registro creado con UUID autogenerado
    MD-->>CT: Objeto ChannelMessage recién creado
    CT->>RS: "Formatea este mensaje para devolverlo"
    RS-->>FE: ✅ 201 Created: { status: "success", data: {uuid, content, sender, ...} }
```

> [!IMPORTANT]
> En una escritura (POST, PUT, DELETE), tanto la **Policy** como el **Form Request** participan, en ese orden. Primero la **Policy** verifica permisos (¿puede este usuario hacer esto?). Luego el **Form Request** valida los datos (¿los campos son correctos?). El Controller nunca recibe una petición que no haya pasado ambas capas.

---

## 4. Mapeo de Archivos en tu Proyecto

Cada capa del diagrama corresponde a una carpeta específica dentro de tu proyecto Laravel:

![Mapeo de carpetas del proyecto a capas de la arquitectura](./assets/mapeo_carpetas.png)

---

## 5. Resumen de Responsabilidades (Regla de Oro)

> [!CAUTION]
> **Cada capa hace UNA SOLA COSA.** Si te encuentras validando datos dentro del Controller, estás rompiendo la arquitectura. Si estás formateando JSON dentro del Model, estás rompiendo la arquitectura. Cada pieza tiene su lugar.

| Capa | ✅ SÍ hace | ❌ NO hace |
|---|---|---|
| **Ruta** | Conectar una URL con un Controller | Validar datos, consultar la BD |
| **Middleware** | Verificar autenticación, limitar peticiones | Lógica de negocio, formatear JSON |
| **Policy** | Verificar permisos del usuario sobre un recurso específico | Validar datos, guardar en BD |
| **Form Request** | Validar y sanitizar los datos entrantes | Guardar en BD, devolver respuestas |
| **Controller** | Coordinar el flujo: recibir, procesar, responder | Escribir SQL directo, formatear JSON a mano |
| **Model** | Definir relaciones, reglas de datos, consultas SQL | Validar datos del frontend, devolver respuestas HTTP |
| **API Resource** | Transformar modelos PHP en JSON limpio | Consultar la BD, validar datos |


