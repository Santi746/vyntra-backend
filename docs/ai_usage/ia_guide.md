# Guia de Modelos opencode Go + Subagentes para Vyntra

> Lista de modelos de IA disponibles en la suscripcion opencode Go y los subagentes especializados para cada tipo de tarea del proyecto.

---

## Modelos disponibles (opencode Go + Zen)

| Modelo | Proveedor | Costo (Zen) | Caracteristica principal |
|---|---|---|---|
| **Nemotron 3 Ultra Free** | NVIDIA | **Gratis** | **550B/55B MoE, 1M ctx, 300+ tok/s, agente principal.** Optimizado para coding agents multi-paso. |
| Kimi K2.6 | Moonshot | $0.95/$4.00 | Baja alucinacion, fuerte en retrieval y analisis factual |
| MiniMax M3 | MiniMax | Go-only | 1M contexto, MSA sparse attention, coding SOTA, producer+verifier loop. **Fallback si Nemotron falla.** |
| ~~MiniMax M2.7~~ | ~~MiniMax~~ | ~~$0.30/$1.20~~ | **Reemplazado por Nemotron 3 Ultra Free** (gratis, mas calidad, sin sobrecoste) |
| DeepSeek V4 Flash | DeepSeek | $0.14/$0.28 | El mas barato, para volumen |
| DeepSeek V4 Pro | DeepSeek | $1.74/$3.48 | Razonamiento profundo PERO 94% alucinacion en AA-Omniscience |
| Qwen3.7 Max | Alibaba | $2.50/$7.50 | Conservador, factual, ideal para teoria |
| GLM-5.1 | Zhipu | $1.40/$4.40 | Estructurado, bueno para documentacion larga |
| MiMo-V2.5 | Xiaomi | Gratis | Rapidisimo, 150K req/mes, ideal para busquedas |

---

## Tabla de responsabilidades: que modelo usar para cada tarea

| Tarea | Modelo | Por que |
|---|---|---|
| **Auditoria completas** (controllers, models, policies, migraciones, vistas, hooks, componentes) + **Verificar frontend vs backend** (API Contract Verify) | Kimi K2.6 | Baja alucinacion, fuerte en retrieval de contexto largo, preciso en contraste factual. Ideal para verificar contra @docs. |
| **Implementar codigo** (controllers, routes, migrations, vistas, hooks, componentes) + **Debugging rapido** (por que falla esto) | **Nemotron 3 Ultra Free** (build) | **Gratis**, 550B/55B MoE, 1M contexto, 300+ tok/s, optimizado para agentic coding. Reemplaza a M3 como agente principal por ser igual o mejor y **costo $0**. M3 queda como fallback si el periodo gratuito de Ultra termina. |
| **Explorar codebase** (encontrar archivos, grepear patrones, responder donde esta X) | MiMo-V2.5 | El mas barato y rapido. 150K peticiones/mes. No necesita potencia, solo velocidad. |
| **Aprender conceptos / teoria / explicaciones** (como funciona Route Model Binding, que es una Policy, etc.) | Qwen3.7 Max | Muy conservador y factual. No se inventa APIs que no existen. Bueno para didactica. |
| **Refactorizar** (cambios multi-archivo, reestructurar) | **Nemotron 3 Ultra Free** | 1M contexto, 300+ tok/s, optimizado para multi-step planning. Lee el proyecto entero y ejecuta refactors complejos. |
| **Generar tests** (Feature, Unit, Browser) | **Nemotron 3 Ultra Free** (via `@test`) | **Gratis**, mejor calidad que M2.7. Al ser gratis no hay sobreingeniería. DeepSeek V4 Flash queda solo para volumen extremo. |
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
model: opencode/nemotron-3-ultra-free
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
|   +- Tarea compleja multi-archivo -> agente principal  (Nemotron 3 Ultra Free)  full tools
|   +- Tarea simple (1 archivo) -> agente principal      (Nemotron 3 Ultra Free)  full tools
|   +- Masivo / bajo riesgo -> agente principal          (DeepSeek V4 Flash)  full tools
|   +- Fallback (si Ultra termina) -> agente principal   (MiniMax M3)  full tools
|
+- ESCRIBIR TESTS
|   +- "@test genera tests para ClubMemberController"  (Nemotron 3 Ultra Free)  en paralelo mientras implementas
|
+- APRENDER / ENTENDER CONCEPTOS
|   +- Agente principal con Qwen3.7 Max como modelo     (conservador, preciso)
|
+- REFACTORIZAR
|   +- Agente principal con Nemotron 3 Ultra Free        (1M contexto, multi-step planning, gratis)
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
   - Vuelves al agente principal (yo) con Nemotron 3 Ultra Free para implementar

3. **Verificar de nuevo**
   - "`@frontend-check` verifica que los cambios coincidan con el frontend"
   - Kimi K2.6 contrasta de nuevo

4. **Generar tests en paralelo**
   - "`@test` genera tests para ClubMemberController"
   - Nemotron 3 Ultra Free escribe tests mientras tu sigues con otra cosa

5. **Documentar el cambio**
   - Agente principal con GLM-5.1 para redactar la retrospectiva o el nuevo doc

---

## Nemotron 3 Ultra Free: posicion vs otros modelos

### A que modelos les gana (reemplaza)

| Modelo | Costo (Zen) | Por que Nemotron Ultra gana |
|---|---|---|
| **MiniMax M3** | Go-only (caro) | Ultra empata en contexto (1M) y supera en velocidad (300+ vs ~100 tok/s), y es **gratis**. Misma capacidad para coding multi-archivo. M3 es ahora el fallback. |
| **DeepSeek V4 Pro** | $1.74/$3.48 | Ultra es **gratis**, mas rapido, y tiene 94% menos riesgo de alucinacion (Pro tiene 94% hallucination rate en AA-Omniscience). |
| **GLM-5.1** | $1.40/$4.40 | Ultra es **gratis**, mejor para coding agents, 1M contexto vs GLM. GLM solo se queda para documentacion estructurada. |
| **Qwen3.7 Max** | $2.50/$7.50 | Ultra es **gratis** y mejor en coding. Qwen solo se queda para teoria/conceptos por ser mas conservador. |

### A que modelos NO les gana (se mantienen)

| Modelo | Por que NO lo reemplaza |
|---|---|
| **Kimi K2.6** | Intelligence Index: Kimi 54 vs Ultra 48. Kimi es significativamente mejor en precision factual y baja alucinacion. Ultra gana en velocidad pero pierde en exactitud. **Kimi se queda para auditoria.** |
| **MiMo-V2.5 Free** | Ambos gratis. MiMo es mas rapido para busquedas simples (especializado en exploracion). Ultra es overkill para "donde esta X". |
| ~~**MiniMax M2.7**~~ | ~~M2.7 cuesta $0.30/$1.20~~ → **Reemplazado por Ultra**. Al ser gratis, no hay sobreingeniería: mejor calidad al mismo costo ($0). |
| **DeepSeek V4 Flash** | $0.14/$0.28 es el mas barato de los de pago. Ideal para volumen masivo sin riesgo. Ultra es gratis pero no necesitas tanta potencia para parches mecanicos. |

### Donde encaja exactamente

```
Nemotron 3 Ultra Free
├── REEMPLAZA a MiniMax M3 como AGENTE PRINCIPAL (build)
│   └── Gratis, igual o mejor en coding, 1M contexto
├── REEMPLAZA a DeepSeek V4 Pro para tareas de razonamiento
│   └── Gratis, menos alucinacion, mas rapido
├── REEMPLAZA a MiniMax M2.7 para generacion de tests
│   └── Gratis, mejor calidad, sin sobreingenieria
├── NO reemplaza a Kimi K2.6 (auditoria)
├── NO reemplaza a MiMo-V2.5 (exploracion)
└── NO reemplaza a DeepSeek V4 Flash (volumen barato)
```

### Riesgo: disponibilidad limitada

Nemotron 3 Ultra Free es "available for a limited time" segun Zen. Si NVIDIA/NVIDIA termina el periodo gratuito:
1. **MiniMax M3** vuelve a ser el agente principal (ya configurado como fallback)
2. Alternativamente: cambiar a `opencode/nemotron-3-ultra` (pago) si aun esta disponible
3. O migrar a otro modelo gratuito que surja

---

## Cuando NO usar subagentes

- Tareas simples (1-2 archivos, un solo cambio) -> mas overhead que beneficio
- Tareas que necesitan tu contexto completo -> el subagente parte de cero
- Debugging activo -> necesitas iteracion rapida en el mismo contexto

## Cuando SI usar subagentes

- **Auditoria** -> subagente con Kimi K2.6, contexto aislado, no contamina tu sesion
- **Exploracion grande** -> subagente con MiMo-V2.5, devuelve respuestas rapidas sin inflar tu chat
- **Cambio de modelo especializado** -> Kimi K2.6 audita, Nemotron 3 Ultra codifica, cada uno con su mejor modelo
- **Tareas paralelas** -> `@test` mientras tu implementas, ambos con contextos independientes
