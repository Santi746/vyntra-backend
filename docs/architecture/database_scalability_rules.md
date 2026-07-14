# 🗄️ INSTRUCCIONES DE SISTEMA: Reglas de Escalabilidad y Base de Datos (Vyntra)

**Contexto para la IA:** Este documento define las reglas estrictas de arquitectura a nivel de Base de Datos para soportar escala masiva en el proyecto Vyntra, actuando como la fuente de la verdad para el diseño de bases de datos.

## 🛑 REGLAS DE ORO DE FILTRADO Y PAGINACIÓN

### 1. El Frontend JAMÁS Filtra ni Ordena Datos Masivos
El frontend (React/Next.js) tiene estrictamente prohibido descargar arreglos gigantes para aplicarles un `.filter()` o `.sort()` localmente.
- **Responsabilidad del Backend/BD:** Todo filtro, ordenamiento y búsqueda pesada se ejecuta físicamente en PostgreSQL, aprovechando los índices B-Tree.
- **Responsabilidad del Frontend:** Solo solicita pequeñas "páginas" o "lotes" de datos (Ej: límite de 10 a 50) para renderizar, delegando la carga pesada al servidor. 

### 2. Paginación por Cursor (OBLIGATORIO)
Prohibido usar `OFFSET` en consultas de alto volumen. A medida que una tabla crece, hacer `OFFSET 500000` obliga a PostgreSQL a leer en disco y descartar medio millón de filas antes de devolver los resultados, colapsando la base de datos.
- **Solución:** Se debe usar Paginación por Cursor (ej: `WHERE created_at < ? ORDER BY created_at DESC LIMIT 15`), la cual salta directamente al registro deseado usando un índice. El frontend ya tiene paginación por cursor con `useInfiniteQuery` cosa que ya se aclaró.

#### 🔄 2.1. Arquitectura de Chat Invertida (Reverse Infinite Scroll)
En sistemas de chat masivos (tipo Discord), cargar los mensajes cronológicamente tradicionales (de viejo a nuevo) requeriría leer *toda* la base de datos para llegar al final. Por lo tanto, **los mensajes se cargan al revés en el Backend**:
- **Backend (BD):** La consulta por defecto carga los mensajes del más **NUEVO** al más **ANTIGUO** (`ORDER BY created_at DESC`). El índice compuesto `['club_channel_uuid', 'created_at']` está diseñado físicamente para resolver esta consulta en 0.00ms.
- **Frontend (Next.js):** Recibe la lista en orden reverso (los más nuevos primero) y utiliza CSS/HTML (como `flex-col-reverse` o manipulación de arrays) para renderizarlos de forma cronológica normal en la pantalla del usuario, colocando el scrollbar al fondo del chat.
- **Paginación Dinámica (Sensor):** Cuando el usuario hace scroll hacia arriba buscando el historial, el "sensor" (Intersection Observer) detecta el tope del chat y solicita el siguiente lote al backend diciendo: *"Dame los siguientes 50 mensajes cuyos `created_at` sean menores (<) que el timestamp de mi mensaje más viejo en pantalla"*.

---

## 🛑 REGLAS DE ÍNDICES Y RESTRICCIONES (CONSTRAINTS)

### 3. Restricciones UNIQUE Compuestas (Protección de Integridad)
Cuando una combinación de dos datos no debe repetirse jamás, la validación en el controlador de Laravel **NO es suficiente**. Se **DEBE** imponer en la base de datos con un constraint `UNIQUE`.
- **Ejemplo Correcto:** `$table->unique('username');`
- **Explicación:** Si un usuario es `hola` y su tag es `#4444`, nadie más en toda la base de datos puede tener esa misma combinación. Si un error del frontend dispara dos peticiones idénticas al mismo milisegundo (Race Condition), PostgreSQL lanza un error a nivel de hardware, protegiendo la base de datos de datos corruptos o duplicados.

### 4. Índices Compuestos para Búsqueda y Ordenamiento
Todo query que filtre por una columna (ej: buscar en un `club_uuid`) y ordene por otra (ej: `joined_at`) **DEBE** tener un Índice Compuesto para evitar escaneos completos de tabla y ordenamientos pesados en memoria RAM ("Filesort").
- **Ejemplo Correcto:** `$table->index(['club_uuid', 'joined_at']);`
- **Explicación:** Esto funciona como un directorio telefónico avanzado. Permite a PostgreSQL ubicar instantáneamente el club y devolver la lista ya pre-ordenada por fecha en fracciones de milisegundo, sin importar si el club tiene 10 miembros o 2 millones.

### 5. Las Llaves Foráneas en PostgreSQL requieren Índice Manual
A diferencia de MySQL (que lo hace solo), PostgreSQL **no crea índices automáticamente** al declarar una llave foránea (`foreignUuid`).
- Si declaras una FK que usarás para filtrar datos (ej: obtener los clubes de un dueño), **ESTÁS OBLIGADO** a añadir `$table->index('owner_uuid')` manualmente.

---

## 🛑 REGLAS PARA OPERACIONES DESTRUCTORAS

### 6. Cero Borrados en Cascada Síncronos (`onDelete('cascade')`) a Gran Escala
Prohibido usar `CASCADE` a nivel de base de datos en tablas masivas (ej: `club_members`, canales, mensajes).
- **El Problema:** Borrar un club que contiene 2 millones de mensajes colapsaría la base de datos entera, ya que la transacción se quedaría bloqueada intentando borrar fila por fila de forma síncrona, tirando abajo toda la aplicación.
- **La Solución Arquitectónica:** Usar **Soft Deletes** (`$table->softDeletes()`). El registro padre (el club) se "oculta" instantáneamente. Un proceso asíncrono (Jobs/Workers en segundo plano) se encarga de limpiar físicamente los registros hijos "huérfanos" en lotes pequeños (ej: 1000 a la vez) para no asfixiar el procesador de la base de datos.
