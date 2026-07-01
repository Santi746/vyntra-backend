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

## Arquitectura Multi-Oficina

El sistema se organiza en 3 dominios:

| Oficina | Llamas vía | Subagentes |
|---------|-----------|------------|
| **Asistente Ejecutor** | Agente principal (TUI) | `@explorar`, `@doc` (huérfanos) |
| **Supervisión de Proyecto** | `@supervision-de-proyecto` | `@audit`, `@front-back-consistency`, `@bestPracticeSenior` |
| **Agente de Testeo** | `@agente-de-testeo` | `@testExecutor`, `@CreateTester`, `@TesterRequests`, `@breakerTester` |

**Regla de oro:** Asistente Ejecutor NUNCA llama a otras oficinas. Tú llamas a `@supervision-de-proyecto` o `@agente-de-testeo` directamente cuando los necesitas.

### Subagentes de Supervisión de Proyecto

#### `@audit` - Auditor de código (Kimi K2.6)

Audita controllers, form requests, routes, models, migrations, componentes y hooks. **Requiere instrucción explícita** de qué auditar. Audita en cascada (controller → resources → form requests). Read-only.

#### `@front-back-consistency` - Verificador FE↔BE (Kimi K2.6)

Verifica consistencia entre frontend y backend: API contract, hooks React Query, controllers, routes. Más autónomo: si no se le especifica qué, audita todo.

#### `@bestPracticeSenior` - Analista senior (DeepSeek V4 Flash)

Analiza buenas prácticas vía webfetch. Solo se usa cuando hay dudas arquitectónicas. Requiere auditoría previa como input.

### Subagentes de Agente de Testeo

#### `@testExecutor` - Ejecutor de tests (DeepSeek V4 Flash)

Ejecuta tests simples o scripts. Requiere instrucción explícita. No para HTTP/WSS complejos.

#### `@CreateTester` - Creador de tests (DeepSeek V4 Flash)

Crea tests: scripts Node.js, planes markdown AI-friendly. Requiere explicación explícita. Sinergia con @TesterRequests.

#### `@TesterRequests` - Tester HTTP/WSS (DeepSeek V4 Flash)

Ejecuta tests de peticiones HTTP/WSS. Requiere script de @CreateTester o del agente. Sabe ejecutar y detectar fallos, NO crear tests.

#### `@breakerTester` - Tester de ruptura (DeepSeek V4 Flash)

Intenta romper el código con estrés, vulnerabilidades, peticiones simultáneas. Usar con precaución.

### Subagentes Huérfanos (disponibles para TODOS)

#### `@explorar` - Explorador rápido (MiMo-V2.5)

Exploración read-only. Encuentra archivos, grepea patrones, responde "dónde está X". Rápido, sin tocar nada.

#### `@doc` - Documentación (DeepSeek V4 Flash)

Crea y mantiene documentación markdown en `docs/`. No toca código.

---

## Arbol de decision: que modelo + subagente usar

```
Que vas a hacer?
|
+- AUDITAR / SUPERVISAR / VERIFICAR CALIDAD
|   +- "@supervision-de-proyecto audita controllers"   (DeepSeek V4 Flash)  orquesta
|   |   +- "@audit" directo si ya sabes qué auditar     (Kimi K2.6)  read-only
|   |   +- "@front-back-consistency" para FE↔BE         (Kimi K2.6)  read-only
|   |
|   +- "@bestPracticeSenior" (solo si hay dudas sr.)    (DeepSeek V4 Flash)  webfetch
|
+- TESTEAR / VERIFICAR FUNCIONALIDAD
|   +- "@agente-de-testeo gestiona el testing"             (DeepSeek V4 Flash)  orquesta
|   |   +- "@CreateTester" para crear planes de test    (DeepSeek V4 Flash)  solo crea
|   |   +- "@TesterRequests" para ejecutar HTTP/WSS     (DeepSeek V4 Flash)  ejecuta
|   |   +- "@testExecutor" para tests simples           (DeepSeek V4 Flash)  ejecuta
|   |   +- "@breakerTester" para romper código          (DeepSeek V4 Flash)  ejecuta
|
+- EXPLORAR / BUSCAR / ENCONTRAR
|   +- Rapido y simple -> "@explorar"                   (MiMo-V2.5)  read-only
|
+- CODIFICAR / IMPLEMENTAR / DEBUGGEAR
|   +- Tarea -> @asistente-ejecutor + @explorar/@doc    (modelo a elección)
|
+- DOCUMENTAR / PLANIFICAR
|   +- "@doc" para docs, planes, prompts                (DeepSeek V4 Flash)
```

---

## Flujo combinado: como encadenar las oficinas

El verdadero poder esta en combinar las oficinas en flujo:

1. **Auditar primero**
   - "`@supervision-de-proyecto` audita los nuevos controllers"
   - Supervisión de Proyecto orquesta: `@audit` revisa código, devuelve informe

2. **Implementar los fixes**
   - "Arregla los problemas 1 y 3 del informe"
   - Vuelves al Asistente Ejecutor para implementar con `@explorar` y `@doc`

3. **Verificar consistencia FE↔BE**
   - "`@supervision-de-proyecto` verifica que los cambios coincidan con el frontend"
   - `@front-back-consistency` contrasta API contract + código

4. **Testear**
   - "`@agente-de-testeo` genera y ejecuta tests para estos cambios"
   - Agente de Testeo orquesta: `@CreateTester` diseña, `@TesterRequests` ejecuta

5. **Documentar el cambio**
   - "`@doc` documenta los cambios y decisiones"

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

## Cuando NO usar oficinas/subagentes

- Tareas simples (1-2 archivos, un solo cambio) -> mas overhead que beneficio
- Tareas que necesitan tu contexto completo -> el subagente parte de cero
- Debugging activo -> necesitas iteracion rapida en el mismo contexto

## Cuando SI usar oficinas/subagentes

- **Auditoria de calidad** -> `@supervision-de-proyecto`, contexto aislado, subagentes especializados
- **Testing completo** -> `@agente-de-testeo`, orquesta create + execute + break
- **Exploracion grande** -> `@explorar`, rapido sin inflar tu chat
- **Documentacion/planes** -> `@doc`, especializado en markdown
- **Dudas de arquitectura** -> `@bestPracticeSenior` via webfetch
- **Tareas paralelas** -> `@agente-de-testeo` mientras implementas con Asistente Ejecutor
