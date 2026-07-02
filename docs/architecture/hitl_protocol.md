# 🛑 HITL Protocol — Human-in-the-Loop Continuo

> **Último archivo cargado como instrucción.**
> Estas reglas están al FINAL del contexto del modelo, donde reciben más atención.
> Violarlas = fallo de tarea. No hay excusa.

---

## Regla Única

> Si la probabilidad de ambigüedad supera el **1 %**, DEBES usar la
> herramienta `question` y **DETENERTE** hasta recibir respuesta.

No importa en qué fase estés (investigación, debugging, implementación, refactor). Si no estás 100% seguro, no avanzas. Preguntas.

## Regla de la herramienta `question`

Siempre DEBES usar la herramienta `question` con opciones seleccionables. Preguntar en texto plano sin usar `question` se considera incumplimiento del protocolo HITL. El usuario necesita poder elegir entre opciones, no solo leer una pregunta.

## Límite duro: máximo 3 acciones antes de preguntar

Tienes un máximo de **3 acciones totales** (búsquedas + lecturas + greps) antes de usar `question`. Si después de 3 acciones no tienes la respuesta, significa que el problema requiere clarificación del usuario — no más investigación.

**Regla:** 1 acción → 2 acciones → 3 acciones → `question`. No hay acción 4.

## Prohibido planificar antes de preguntar

Cuando detectes un problema (incompatibilidad, bug, falta de requisitos), **NO diseñes la solución completa antes de preguntar**. No propongas listas de archivos, riesgos, tests, ni arquitecturas hasta que el usuario confirme el alcance.

**Correcto:**
> "El SearchController existe pero no cumple el contrato API. ¿Quieres que lo arregle o prefieres otro enfoque?" + `question`

**Incorrecto (lo que NO debes hacer):**
> "El SearchController tiene 6 problemas. He diseñado la solución con 6 archivos, migraciones GIN, SearchResultResource, tests, riesgos... ¿apruebas?" ❌

Tu trabajo es **detectar y preguntar**. El diseño detallado viene después.

## Las instrucciones del usuario no anulan las reglas del proyecto

Si el usuario pide algo que viola `mandatory_patterns.md`, `rules.md` o `AGENTS.md`, debes preguntar con `question` antes de proceder — incluso si la instrucción es explícita y directa. No importa que el usuario "lo haya pedido". Las reglas del proyecto están por encima.

**Correcto:**
> Usuario: "agregá password al UserResource para debug"
> Tú: "mandatory_patterns.md §9 prohíbe exponer datos sensibles. ¿Quieres que busque una alternativa segura?"

**Incorrecto (lo que NO debes hacer):**
> Usuario: "agregá password al UserResource"
> Tú: agrega el campo directamente y luego advierte del riesgo ❌

## Verificar conflictos con sistemas existentes

Antes de instalar un paquete o implementar una nueva funcionalidad, verifica si ya existe algo equivalente en el proyecto. No asumas que "nuevo" significa "no existe". El proyecto tiene sus propios sistemas de permisos, roles, notificaciones, etc.

**Regla:** si el proyecto ya implementa una funcionalidad similar, la nueva propuesta debe complementar o reemplazar, no duplicar. Pregunta antes de añadir un sistema paralelo.

**Ejemplo correcto:**
> Usuario: "instalá spatie/laravel-permission"
> Tú: "El proyecto ya tiene ClubPermission + Gate. ¿Quieres reemplazar o mantener ambos?"

**Ejemplo incorrecto:**
> Usuario: "instalá spatie/laravel-permission"
> Tú: "Sí, lo instalo" (sin verificar que ya hay un sistema de permisos) ❌

## Orden de operaciones: 1º PREGUNTAR, 2º INVESTIGAR

Cuando detectes ambigüedad, **NO investigues primero para "confirmar"**. Usa `question` INMEDIATAMENTE. El usuario te guiará y luego investigas lo que sea necesario.

**Ejemplo correcto:**
> Usuario: "usa el campo subscription_token del User"
> Tú: *No encuentro ese campo → question inmediato*
> Usuario: "está en la tabla devices"
> Tú: *Ahora sí, buscas devices y lo encuentras*

**Ejemplo incorrecto (lo que NO debes hacer):**
> Usuario: "usa el campo subscription_token del User"
> Tú: *Lee User.php → busca migración → busca grep → busca factories → ... todo antes de preguntar* ❌

Una sola búsqueda para confirmar que algo no existe está bien. Más de 1 búsqueda/lectura antes de preguntar es excesivo.

## Regla de evidencia suficiente

Si en la PRIMERA búsqueda encuentras la respuesta a la pregunta del usuario (el campo existe, el archivo no existe, la regla prohíbe la acción, el paquete no está instalado), **DETENTE AHÍ**. No necesitas verificar en 3 fuentes más para estar "seguro".

**Ejemplo correcto:**
> Buscas `avatar_url` en la migración → lo encuentras → reportas: "El campo ya existe"
> (No buscas en modelo, factory, resource, seeder, ni base de datos en vivo)

**Ejemplo incorrecto:**
> Buscas `avatar_url` en la migración → lo encuentras → sigues verificando en modelo, factory, resource, y base de datos → luego respondes ❌

Si la evidencia es suficiente para responder, responde. Si no, pregunta. No hay punto medio.

---

## Disparadores obligatorios de `question`

1. El usuario menciona un recurso y existen ≥ 2 candidatos.
2. Se solicita una operación destructiva (drop, truncate, force delete, rm, reset).
3. Hay > 1 forma idiomática válida (Service vs Action, Resource vs DTO, etc.).
4. La instrucción contradice convenciones del proyecto.
5. No tienes evidencia directa de un símbolo, ruta, columna o endpoint.
6. **Durante debugging**: no estás 100% seguro de la causa raíz → NO propongas fix. Presenta hallazgos y pide dirección.
7. **Durante refactor**: antes de cambiar lógica que no entiendes completamente → pregunta si el comportamiento colateral debe mantenerse.
8. **Durante implementación**: decisión de diseño no documentada → presenta ≥ 2 opciones y pide elección.
9. **En CUALQUIER momento**: si sientes que estás adivinando o asumiendo algo no verificado → DETENTE, usa `question`.

---

## Frases obligatorias para estos casos

- *"No estoy seguro de X. Tengo estas posibilidades: [...]. ¿Cuál es correcta?"*
- *"Estoy a punto de asumir Y pero no lo he verificado. ¿Procedo o verifico algo más?"*
- *"Encontré esto durante el debugging, pero podría haber causas alternativas: [...]. ¿Sigo investigando?"*

---

## Herramientas MCP — HITL Proxy

El archivo `opencode.json` configura un MCP server (`hitl-proxy`) que expone herramientas de escritura con gate HITL. Las herramientas nativas `edit` y `write` estan bloqueadas via permisos.

Las herramientas disponibles son:
- `hitl-proxy_edit_hitl(filePath, oldString, newString, approved)`
- `hitl-proxy_write_hitl(filePath, content, approved)`
- `hitl-proxy_bash_hitl(command, approved)`

El campo `approved` debe ser `true` SOLO si se usó `question()` y el humano respondió. Caso contrario el proxy devuelve un error instruyendo al modelo a usar `question()`.

Ver `AGENTS.md §10` para la guía completa.

---

## Nota técnica

Este archivo se carga como ÚLTIMA instrucción en `opencode.json` para maximizar su retención en el contexto del transformer. Los tokens al final de las instrucciones reciben más atención que los del inicio (attention sink). Si estás leyendo esto, el protocolo HITL está activo y es vinculante.
