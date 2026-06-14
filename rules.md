# rules.md — Convenciones del Proyecto Vyntra

> Reglas verificables. El agente DEBE comprobar cumplimiento antes de editar.
> Lectura obligatoria junto a `AGENTS.md`.
> 
> **Nota**: Las secciones marcadas como `[ESPECÍFICO DEL STACK]` deben adaptarse si se replica esta arquitectura en otro proyecto. Las reglas universales aplican a cualquier proyecto. Este proyecto es la version LARAVEL 13 nunca asumir que es laravel 11 o 12 eso es un error laravel 13 fue publicada el 2026 y es la que se esta usando ahora mismo

---

## 1. REGLAS UNIVERSALES (aplican a todo proyecto)

### 1.1 Verificar antes de asumir

Antes de cualquier afirmación sobre el proyecto, consulta:
1. `docs/architecture/` para patrón y capas.
2. `docs/api_contract.md` para forma de respuestas.
3. `docs/api_requests_manifest.md` para requests soportadas.
4. `docs/project_steps_guides/` para pasos canónicos.
5. Archivos reales del proyecto (NO helpers generados como `_ide_helper*`).

### 1.2 Principios de diseño

- **Separación de responsabilidades**: Cada archivo/clase tiene una sola razón de cambio.
- **DRY**: No duplicar lógica. Extraer a funciones, servicios o componentes compartidos.
- **KISS**: Solución más simple que funcione. Complejidad solo cuando sea necesaria.
- **Consistencia**: Seguir el estilo dominante del proyecto, no el preferido personal.

### 1.3 Convenciones de nombrado

- **Archivos**: `snake_case` para backend, `PascalCase` para componentes/frontend, `kebab-case` para URLs/assets.
- **Funciones/Métodos**: Verbos descriptivos (`getUser`, `createOrder`, `validateInput`).
- **Variables**: Nombres que expresen intención (`userList` no `data`, `isValid` no `flag`).
- **Constantes**: `UPPER_SNAKE_CASE` para valores inmutables.

### 1.4 Manejo de errores

- Nunca silenciar errores sin justificación documentada.
- Mensajes de error útiles para debugging, genéricos para el usuario final.
- Logs estructurados con contexto relevante (no solo "error occurred").
- Validar inputs en el borde (API, formularios, CLI).

### 1.5 Seguridad

- **No commitear** secretos, tokens, credenciales o `.env`.
- Validar y sanitizar todos los inputs del usuario.
- Usar prepared statements/ORM para queries (nunca string concatenation).
- Autenticación y autorización en cada endpoint protegido.
- HTTPS obligatorio en producción.

### 1.6 Testing

- Tests aislados: cada test verifica una sola cosa.
- Naming claro: `test_user_cannot_access_admin_panel`.
- Cobertura mínima: camino feliz + 1 caso de error + 1 caso de autorización.
- Tests rápidos: sin dependencias externas innecesarias.
- Mockear servicios externos (APIs, emails, pagos).

### 1.7 Git y Commits

- Commits atómicos: un cambio lógico por commit.
- Mensajes descriptivos: `feat: add user profile endpoint`, `fix: handle null email gracefully`.
- No commitear archivos generados, dependencias o configuraciones locales.
- Revisar diff antes de commitear.

---

## 2. CONVENCIONES DEL BACKEND [ESPECÍFICO: Laravel 13]

### 2.1 Controllers

- Ubicación: `app/Http/Controllers/Api/...`.
- Validación SIEMPRE vía FormRequest (no `$request->validate()` inline).
- Respuesta vía API Resource o `response()->json(...)` consistente con `docs/api_contract.md`.
- Status codes correctos: 200/201/204/400/401/403/404/409/422/500.
- Sin lógica de negocio: delegar a Service/Action si existe el patrón.

### 2.2 Models

- Eloquent: `$fillable` declarado, jamás `$guarded = []` sin justificar.
- Casts en `$casts` (no en accessors si es serialización trivial).
- Relaciones tipadas: `public function user(): BelongsTo`.
- Scopes con prefijo `scope`.
- La verdad está en la migración, no en `_ide_helper_models.php`.

### 2.3 API Resources

- Ubicación: `app/Http/Resources/`.
- Nombre `XxxResource` o `XxxCollection`.
- Solo exponer campos definidos en `docs/api_contract.md`.
- Si añades un campo: actualiza el contrato y avisa al usuario.

### 2.4 FormRequests

- Ubicación: `app/Http/Requests/`.
- `authorize()` con lógica real (no `return true` sin justificar).
- `rules()` exhaustivo, mensajes en `messages()` cuando aplique.

### 2.5 Routes

- API en `routes/api.php`, web en `routes/web.php`.
- Agrupar por prefijo y middleware (`auth:sanctum`).
- Nombrar rutas: `Route::...->name('recurso.accion')`.
- Verificar conflictos con `php artisan route:list`.

### 2.6 Migrations

- Una migración por cambio atómico.
- Foreign keys con `constrained()` y `onDelete` explícito.
- Nunca editar una migración ya aplicada; crear una nueva.

### 2.7 Tests (Backend)

- Feature tests en `tests/Feature/`, unit en `tests/Unit/`.
- Usar `RefreshDatabase` cuando se toca DB.
- Sanctum: `actingAs($user, 'sanctum')`.
- Factory por cada modelo nuevo en `database/factories/`.

---

## 3. CONVENCIONES DEL FRONTEND [ESPECÍFICO: Vyntra Frontend]

### Stack técnico

- **Framework**: Next.js 16 (App Router) con React 19
- **Estilos**: Tailwind CSS v4 + PostCSS
- **Estado global**: Zustand (`src/shared/stores/`)
- **Data fetching**: TanStack React Query v5 (`@tanstack/react-query`)
- **UI**: Framer Motion, Lucide React, React Icons, Sonner (toasts)
- **Lint/Format**: ESLint 9 + Prettier + prettier-plugin-tailwindcss
- **Compilador**: React Compiler habilitado en `next.config.mjs`

### 3.1 Estructura de directorios

```
src/
├── app/              # Next.js App Router (rutas, layouts, pages)
├── features/         # Módulos por dominio (auth, chat, clubs, dashboard, etc.)
│   └── {feature}/
│       └── components/  # Componentes específicos del feature
├── services/         # Capa de servicios/API (chat.service.js, user.service.js, etc.)
├── shared/           # Código reutilizable entre features
│   ├── components/   # Componentes UI genéricos (providers/, ui/)
│   ├── constants/    # Constantes globales
│   ├── context/      # React Context providers
│   ├── hooks/        # Custom hooks reutilizables
│   ├── stores/       # Zustand stores
│   └── utils/        # Funciones utilitarias
└── globals.css       # Estilos globales
```

### 3.2 Convenciones de archivos

- **Componentes**: `PascalCase.jsx` (ej: `SidebarTemplate.jsx`, `AOSInit.jsx`)
- **Hooks**: `useCamelCase.js` o `useCamelCase.jsx` (ej: `useBreakpointValue.js`)
- **Services**: `snake_case.service.js` (ej: `user.service.js`, `chat.service.js`)
- **Stores**: `useCamelCaseStore.js` (ej: `useReplyStore.js`)
- **Layouts/Pages**: `layout.js`, `page.js` (convención Next.js App Router)
- **Utilidades**: `camelCase.utils.js` (ej: `mock.utils.js`)

### 3.3 Componentes

- Un componente por archivo.
- Usar `"use client"` solo cuando sea necesario (estado local, event listeners, browser APIs).
- Preferir Server Components por defecto (sin `"use client"`).
- Props documentadas con JSDoc `@param` y `@returns`.
- Sin lógica de negocio compleja: extraer a hooks o services.
- Componentes reutilizables van en `src/shared/components/`.
- Componentes específicos de un feature van en `src/features/{feature}/components/`.

### 3.4 Custom Hooks

- Prefijo `use` obligatorio.
- Ubicados en `src/shared/hooks/` si son reutilizables.
- Documentados con JSDoc incluyendo `@example`.
- Un hook por archivo.
- Manejar cleanup en `useEffect` (removeEventListener, clearInterval, etc.).

### 3.5 Servicios (API Layer)

- Ubicados en `src/services/`.
- Nombrado: `{entity}.service.js` (ej: `user.service.js`).
- Exportar como objeto con métodos: `export const UserService = { ... }`.
- Cada método documentado con JSDoc (`@service`, `@description`, `@param`, `@returns`).
- Retornar estructura consistente: `{ status: "success", data: ... }`.
- Manejar delays/mock con `mockRequest()` durante desarrollo.
- No mezclar lógica de UI con lógica de datos.

### 3.6 Estado Global (Zustand)

- Stores en `src/shared/stores/`.
- Nombrado: `use{Entity}Store.js`.
- Mantener stores mínimos: solo estado verdaderamente global.
- Preferir React Query para estado de servidor (cache, refetch, etc.).

### 3.7 Estilos

- Tailwind CSS v4 con `@tailwindcss/postcss`.
- Mobile-first responsive design.
- Usar clases de Tailwind directamente en JSX (no CSS modules).
- Colores del tema definidos en `globals.css` (ej: `bg-forest-dark`).
- Prettier con `prettier-plugin-tailwindcss` para ordenar clases automáticamente.

### 3.8 Integración con Backend

- Tipos/interfaces sincronizados con `docs/api_contract.md` del backend.
- Services como capa intermedia entre componentes y API.
- React Query para cache, refetch automático y manejo de loading/error states.
- Tokens y auth headers gestionados centralmente (en service layer o interceptor).
- Usar `@frontend-check` para verificar consistencia con el backend.

### 3.9 Providers y Context

- Providers globales en `src/shared/components/providers/Providers.jsx`.
- Context en `src/shared/context/` (ej: `NavigationContext`).
- Envolver la app en `src/app/layout.js`.
- No crear context innecesario: preferir props o Zustand cuando aplique.

### 3.10 Animaciones y UX

- Framer Motion para animaciones complejas.
- AOS (Animate On Scroll) para animaciones de entrada.
- `react-intersection-observer` para lazy loading y triggers de scroll.
- `react-loading-skeleton` para loading states.
- `react-virtuoso` para listas virtuales (rendimiento con muchos items).
- Sonner para notificaciones/toasts.

---

## 4. CONVENCIONES DE INTEGRACIÓN (Backend ↔ Frontend)

### 4.1 Contrato API

- `docs/api_contract.md` (backend) es la fuente de verdad.
- Cualquier cambio en el contrato requiere actualización en ambos lados.
- Versionado de API si hay breaking changes.

### 4.2 Verificación de Consistencia

- Usar `@frontend-check` para verificar que backend y frontend coincidan.
- Campos, tipos, rutas y respuestas deben estar sincronizados.
- Documentar discrepancias temporales con `TODO` y justificación.

---

## 5. ESTILO Y FORMATEO

### Backend
- PSR-12 + Pint (`./vendor/bin/pint`).
- Imports ordenados (alfabético o por grupos según convención).
- StrictTypes solo si ya es estilo dominante en la capa.

### Frontend
- ESLint 9 + Prettier (`npm run lint`).
- Prettier con plugin de Tailwind para ordenar clases.
- React Compiler habilitado (no optimizaciones manuales de memo/useCallback innecesarias).
- Sin `console.log` en código de producción.
- Comentarios solo para explicar "por qué", no "qué".

---

## 6. RECORDATORIOS CRÍTICOS

- **No tocar** archivos generados (`_ide_helper*`, `vendor/`, `node_modules/`, `storage/`, `bootstrap/cache/`, `.next/`).
- **No commitear** secretos: leer/escribir `.env` solo con aprobación explícita.
- **No correr** comandos destructivos (`migrate:fresh`, `db:wipe`, `optimize:clear`) sin `question` previa.
- **Antes de instalar** paquetes (Composer/NPM): pedir aprobación con `question`.
- **Antes de crear** nuevos patrones/archivos: verificar que no exista ya un patrón dominante.
- **No modificar** `.next/` (directorio de build de Next.js, se regenera).
- **No commitear** `package-lock.json` sin revisar cambios (puede incluir dependencias no deseadas).
