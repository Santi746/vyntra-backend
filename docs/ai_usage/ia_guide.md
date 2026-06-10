# Guia de Modelos opencode Go + Subagentes para Vyntra

> Lista de modelos de IA disponibles en la suscripcion opencode Go y los subagentes especializados para cada tipo de tarea del proyecto.

---

## Modelos disponibles (opencode Go)

| Modelo | Proveedor | Caracteristica principal |
|---|---|---|
| Kimi K2.6 | Moonshot | Baja alucinacion, fuerte en retrieval y analisis factual |
| MiniMax M3 | MiniMax | 1M contexto, MSA sparse attention, coding SOTA, producer+verifier loop |
| MiniMax M2.7 | MiniMax | Rapido, barato, 10B params activos, bueno para coding simple |
| DeepSeek V4 Flash | DeepSeek | El mas barato ($0.14/M input), para volumen |
| DeepSeek V4 Pro | DeepSeek | Razonamiento profundo PERO 94% alucinacion en AA-Omniscience |
| Qwen3.7 Max | Alibaba | Conservador, factual, ideal para teoria |
| GLM-5.1 | Zhipu | Estructurado, bueno para documentacion larga |
| MiMo-V2.5 | Xiaomi | Rapidisimo, 150K req/mes, ideal para busquedas |

---

## Tabla de responsabilidades: que modelo usar para cada tarea

| Tarea | Modelo | Por que |
|---|---|---|
| **Auditoria completas** (controllers, models, policies, migraciones, vistas, hooks, componentes) + **Verificar frontend vs backend** (API Contract Verify) | Kimi K2.6 | Baja alucinacion, fuerte en retrieval de contexto largo, preciso en contraste factual. Ideal para verificar contra @docs. |
| **Implementar codigo** (controllers, routes, migrations, vistas, hooks, componentes) + **Debugging rapido** (por que falla esto) | MiniMax M3 | 1M contexto, MSA sparse attention para velocidad, coding SOTA, producer+verifier adversarial loop. M3 cubre ambos: implementacion nueva y diagnosis de errores. |
| **Explorar codebase** (encontrar archivos, grepear patrones, responder donde esta X) | MiMo-V2.5 | El mas barato y rapido. 150K peticiones/mes. No necesita potencia, solo velocidad. |
| **Aprender conceptos / teoria / explicaciones** (como funciona Route Model Binding, que es una Policy, etc.) | Qwen3.7 Max | Muy conservador y factual. No se inventa APIs que no existen. Bueno para didactica. |
| **Refactorizar** (cambios multi-archivo, reestructurar) | MiniMax M3 | M3 tiene 1M de contexto (512K minimo en algunos escenarios de opencode Go) y producer+verifier loop. Puede ver el proyecto entero de una vez. |
| **Generar tests** (Feature, Unit, Browser) | MiniMax M2.7 o DeepSeek V4 Flash | Tests son repetitivos, no requieren razonamiento frontera. M2.7 para calidad, DeepSeek Flash para volumen. |
| **Escribir documentacion / @docs** | GLM-5.1 | Bueno en analisis estructurado y salida larga formateada. Ideal para redactar AGENTS.md, retros, guias. |
| **Refactor / parches masivos baratos** (cambios mecanicos de bajo riesgo, migraciones de estilo) | DeepSeek V4 Flash | $0.14/M input. Para volumen > calidad. No usar para logica critica. |

---

## Subagentes especializados

Crear los siguientes archivos en `.opencode/agents/`. Cada subagente corre con su propio contexto aislado, modelo dedicado y permisos restrictivos.

### 1. `@auditar` - Auditor del backend

Archivo: `.opencode/agents/auditar.md`

```yaml
---
description: Auditoria completa del backend Laravel. Sigue @docs y RULEs para verificar escalabilidad, arquitectura, seguridad y errores. Usa cuando pidas "analiza todo el backend", "revisa este modulo contra las reglas".
mode: subagent
model: kimi-k2.6
temperature: 0.1
permission:
  edit: deny
  bash:
    "*": deny
    "git diff*": allow
    "git log*": allow
---

Eres un auditor de backend Laravel. Tu trabajo:

1. Lee TODOS los archivos relevantes (controllers, models, routes, policies, form requests, migrations)
2. Verifica cada uno contra los @docs del proyecto y las reglas de arquitectura
3. Reporta: violaciones de arquitectura, problemas de escalabilidad, errores de consistencia, codigo muerto, endpoints faltantes
4. NO edites archivos. Solo reporta hallazgos.
5. Se preciso. Si algo parece raro pero no tienes certeza, marcalo como "ADVERTENCIA: a verificar"
```

### 2. `@explorar` - Explorador rapido de codigo

Archivo: `.opencode/agents/explorar.md`

```yaml
---
description: Exploracion rapida de codigo. Encuentra archivos, busca patrones, responde preguntas sobre la estructura del proyecto. Read-only.
mode: subagent
model: mimo-v2.5
permission:
  edit: deny
  bash:
    "*": deny
    "git status": allow
    "git diff*": allow
    "rg *": allow
    "grep *": allow
---

Explorador de codigo. Read-only. Encuentra archivos, grepea patrones, contesta "donde esta X", "cuantos archivos usan Y". Rapido y sin tocar nada.
```

### 3. `@frontend-check` - Verificador de consistencia frontend-backend

Archivo: `.opencode/agents/frontend-check.md`

```yaml
---
description: Verifica que el backend y frontend coincidan. Revisa contratos de API, tipos, campos de recursos y rutas. Tambien conocido como API Contract Verify.
mode: subagent
model: kimi-k2.6
temperature: 0.1
permission:
  edit: deny
---

Verificador de consistencia frontend-backend (API Contract Verify):

1. Lee los API Resources, controllers, y routes del backend
2. Lee los tipos/interfaces y llamadas API del frontend
3. Reporta discrepancias: campos faltantes, tipos incorrectos, rutas huerfanas, respuestas que el frontend no espera
4. NO edites, solo reporta
```

### 4. `@test` - Generador de tests

Archivo: `.opencode/agents/test.md`

```yaml
---
description: Genera tests para controllers, models y features del backend Laravel.
mode: subagent
model: minimax-m2.7
permission:
  edit: allow
---

Generador de tests para Laravel:

1. Lee el controller/model a testear
2. Escribe tests en tests/Feature/ o tests/Unit/ siguiendo el estilo del proyecto
3. Usa RefreshDatabase, factories, y Sanctum actingAs
```

---

## Arbol de decision: que modelo + subagente usar

```
Que vas a hacer?
|
+- AUDITAR / ANALIZAR / VERIFICAR REGLAS / VERIFICAR FRONTEND-BACKEND
|   +- "@auditar analiza todos los controllers"        (Kimi K2.6)  read-only
|   +- "@frontend-check verifica el API contract"       (Kimi K2.6)  read-only
|   +- "@auditar analiza todos los models/policies"     (Kimi K2.6)  read-only
|
+- EXPLORAR / BUSCAR / ENCONTRAR
|   +- Rapido y simple -> "@explorar"                   (MiMo-V2.5)  read-only
|   +- Documentacion externa -> "@scout"                (ya viene con opencode)
|
+- CODIFICAR / IMPLEMENTAR / DEBUGGEAR
|   +- Tarea compleja multi-archivo -> agente principal  (MiniMax M3)  full tools
|   +- Tarea simple (1 archivo) -> agente principal      (MiniMax M3)  full tools
|   +- Masivo / bajo riesgo -> agente principal          (DeepSeek V4 Flash)  full tools
|
+- ESCRIBIR TESTS
|   +- "@test genera tests para ClubMemberController"  (M2.7)  en paralelo mientras implementas
|
+- APRENDER / ENTENDER CONCEPTOS
|   +- Agente principal con Qwen3.7 Max como modelo     (conservador, preciso)
|
+- REFACTORIZAR
|   +- Agente principal con MiniMax M3                  (1M contexto, producer+verifier)
|
+- DOCUMENTAR
|   +- Agente principal con GLM-5.1                     (estructurado, salidas largas)
|
+- REFACTOR / PARCHEOS MASIVOS BARATOS
|   +- Agente principal con DeepSeek V4 Flash           ($0.14/M input)
```

---

## Flujo combinado: como encadenar modelos

El verdadero poder esta en combinar modelos en flujo:

1. **Auditar primero**
   - "`@auditar` analiza todos los controllers"
   - Kimi K2.6 lee todo, devuelve informe de violaciones

2. **Implementar los fixes**
   - "Ok, arregla los problemas 1 y 3 del informe"
   - Vuelves al agente principal (yo) con M3 para implementar

3. **Verificar de nuevo**
   - "`@frontend-check` verifica que los cambios coincidan con el frontend"
   - Kimi K2.6 contrasta de nuevo

4. **Generar tests en paralelo**
   - "`@test` genera tests para ClubMemberController"
   - M2.7 escribe tests mientras tu sigues con otra cosa

5. **Documentar el cambio**
   - Agente principal con GLM-5.1 para redactar la retrospectiva o el nuevo doc

---

## Cuando NO usar subagentes

- Tareas simples (1-2 archivos, un solo cambio) -> mas overhead que beneficio
- Tareas que necesitan tu contexto completo -> el subagente parte de cero
- Debugging activo -> necesitas iteracion rapida en el mismo contexto

## Cuando SI usar subagentes

- **Auditoria** -> subagente con Kimi K2.6, contexto aislado, no contamina tu sesion
- **Exploracion grande** -> subagente con MiMo-V2.5, devuelve respuestas rapidas sin inflar tu chat
- **Cambio de modelo especializado** -> Kimi K2.6 audita, M3 codifica, cada uno con su mejor modelo
- **Tareas paralelas** -> `@test` mientras tu implementas, ambos con contextos independientes
