# 🎨 Auth Layer Guide — Implementación Frontend

> **Modelo de identidad: Discord moderno** ✅
>
> Vyntra usa `username` único global como identificador principal. Sin discriminador numérico.
>
> **Reglas inquebrantables:**
> - `username` es único global (UNIQUE en DB). El user lo elige.
> - `username ≠ first_name/last_name`. `username` es el @handle de mención.
>   `first_name` y `last_name` son el nombre real.
> - Tras OAuth (Google/GitHub/Discord), el user llega con `username = null`.
>   El backend le responde con `profile_completed: false`. El frontend debe
>   redirigirlo a `/auth/complete-profile` para que complete los 3 datos de oro:
>   `username`, `first_name`, `last_name`.

---

> **Versión:** 1.0  
> **Stack:** Next.js 16 + React 19 + TanStack React Query v5 + Zustand  
> **Repositorio:** `vyntra-frontend` (ruta: `C:\Users\squev\Documents\vyntra-frontend`)  
> **Rama:** `feature/auth`  
> **Propósito:** Conectar el frontend (actualmente 100% mock) a la API real de autenticación Laravel

---

## 📋 Índice

1. [Conceptos Fundamentales](#1-conceptos-fundamentales)
2. [Arquitectura del Frontend Auth](#2-arquitectura-del-frontend-auth)
3. [Paso 1: Crear auth.service.js](#3-paso-1-crear-authservicejs)
4. [Paso 2: Crear useAuthStore.js (Zustand)](#4-paso-2-crear-useauthstorejs-zustand)
5. [Paso 3: Crear useAuth.js (Hooks React Query)](#5-paso-3-crear-useauthjs-hooks-react-query)
6. [Paso 4: Modificar LoginForm.jsx](#6-paso-4-modificar-loginformjsx)
7. [Paso 5: Modificar RegisterForm.jsx](#7-paso-5-modificar-registerformjsx)
8. [Paso 6: Crear página /auth/callback (Socialite)](#8-paso-6-crear-página-authcallback-socialite)
9. [Paso 7: Crear página /auth/2fa (2FA)](#9-paso-7-crear-página-auth2fa-2fa)
10. [Paso 8: Crear página /settings/sessions](#10-paso-8-crear-página-settingssessions)
11. [Paso 9: Crear página /settings/security](#11-paso-9-crear-página-settingssecurity)
12. [Verificación Final](#12-verificación-final)

---

## 1. Conceptos Fundamentales

### ¿Qué vamos a construir?

Vyntra usa **Sanctum** para autenticación con **Bearer tokens**. El frontend debe:

1. **Enviar credenciales** al backend (email + password)
2. **Recibir un token** y guardarlo en un store persistente (Zustand con `persist`)
3. **Enviar el token** en cada petición subsecuente (header `Authorization: Bearer {token}`)
4. **Eliminar el token** al cerrar sesión

### Las 3 capas del frontend auth

```
┌─────────────────────────────────────────────────────────────┐
│                     CAPA DE PRESENTACIÓN                      │
│  src/features/auth/components/organisms/                     │
│  LoginForm.jsx · RegisterForm.jsx                             │
│  (Componentes React con "use client")                         │
├─────────────────────────────────────────────────────────────┤
│                     CAPA DE LÓGICA (Hooks)                    │
│  src/shared/hooks/useAuth.js                                  │
│  (React Query: useMutation para login/register/logout)        │
├─────────────────────────────────────────────────────────────┤
│                     CAPA DE ESTADO                            │
│  src/shared/stores/useAuthStore.js                            │
│  (Zustand + persist: token, user, isAuthenticated)            │
├─────────────────────────────────────────────────────────────┤
│                     CAPA DE SERVICIOS                         │
│  src/services/auth.service.js                                 │
│  (fetchApi → POST /api/auth/login, etc.)                      │
├─────────────────────────────────────────────────────────────┤
│                     BACKEND (Laravel API)                     │
│  POST /api/auth/login · POST /api/auth/register               │
│  POST /api/auth/logout · GET /api/auth/social/{provider}      │
└─────────────────────────────────────────────────────────────┘
```

### Flujo de datos completo

```
LoginForm                    useAuth (hook)           auth.service          Backend
 ─────────                   ──────────────           ────────────          ───────
    │                            │                        │                    │
    │  handleSubmit(email, pw)   │                        │                    │
    ├──────────────────────────► │                        │                    │
    │                            │  loginMutation.mutate  │                    │
    │                            ├──────────────────────► │                    │
    │                            │                        │  fetch POST /login  │
    │                            │                        ├──────────────────► │
    │                            │                        │                    │
    │                            │                        │◄───────────────── │
    │                            │                        │  { user, token }   │
    │                            │◄───────────────────────┤                    │
    │                            │                        │                    │
    │                            │  setAuth(user, token)  │                    │
    │                            │  (Zustand store)       │                    │
    │                            ├──► useAuthStore        │                    │
    │                            │                        │                    │
    │                            │  toast.success()       │                    │
    │                            │  router.push('/')      │                    │
    │◄───────────────────────────┤                        │                    │
    │                            │                        │                    │
```

### Convenciones del proyecto que debes conocer

| Concepto | Detalle |
|:---|:---|
| **Servicios** | `src/services/{nombre}.service.js`, export como objeto `export const AuthService = { ... }` |
| **Stores** | `src/shared/stores/use{nombre}Store.js`, Zustand con `persist` + `partialize` |
| **Hooks** | `src/shared/hooks/use{nombre}.js`, funciones que retornan mutations/queries |
| **Componentes** | PascalCase.jsx, feature-specific en `src/features/{feature}/components/` |
| **"use client"** | Solo cuando hay hooks/eventos/estado/browser APIs |
| **snake_case** | En propiedades de datos (refleja el backend) |
| **mockRequest()** | Se MANTIENE en desarrollo — simula latencia de red |

### Endpoints que consumiremos

| Método | Endpoint | Propósito | Auth |
|:---:|:---|:---|:---:|
| POST | `/api/auth/register` | Registrar usuario | ❌ |
| POST | `/api/auth/login` | Iniciar sesión | ❌ |
| POST | `/api/auth/logout` | Cerrar sesión | ✅ |
| GET | `/api/auth/social/{provider}` | Redirigir a Google OAuth | ❌ |
| POST | `/api/auth/forgot-password` | Solicitar reset de password | ❌ |
| POST | `/api/auth/reset-password` | Ejecutar reset de password | ❌ |
| POST | `/api/auth/email/verification-notification` | Reenviar verificación email | ✅ |
| GET | `/api/user/sessions` | Listar sesiones activas | ✅ |
| DELETE | `/api/user/sessions/{uuid}` | Revocar sesión | ✅ |

---

## 2. Arquitectura del Frontend Auth

### Árbol de archivos (lo que vamos a crear/modificar)

```
vyntra-frontend/src/
├── services/
│   └── auth.service.js                     [CREAR] Servicio de API auth
│
├── shared/
│   ├── stores/
│   │   └── useAuthStore.js                 [CREAR] Zustand store persistente
│   └── hooks/
│       └── useAuth.js                      [CREAR] Hooks de auth (React Query)
│
├── features/auth/
│   └── components/organisms/
│       ├── LoginForm.jsx                   [MODIFICAR] Conectar a API real
│       └── RegisterForm.jsx                [MODIFICAR] Agregar campos + conectar
│
├── app/
│   └── auth/
│       ├── callback/
│       │   └── page.js                     [CREAR] Socialite callback
│       └── 2fa/
│           └── page.js                     [CREAR] Pantalla de código 2FA
│
└── app/settings/
    ├── sessions/
    │   └── page.js                         [CREAR] Gestión de dispositivos
    └── security/
        └── page.js                         [CREAR] 2FA + email verification
```

---

## 3. Paso 1: Crear auth.service.js

**Archivo:** `src/services/auth.service.js`

### ¿Qué hace este archivo?

Es la **capa de comunicación HTTP** con el backend. Cada método encapsula un endpoint de la API de autenticación. El patrón es idéntico al de `ClubService` y `UserService` existentes.

### Código completo

```javascript
/**
 * @service AuthService
 * @description Maneja la autenticación de usuarios: registro, login, logout,
 *              social login, 2FA, sesiones y verificación de email.
 *
 * Convención: cada método recibe los parámetros necesarios, llama a fetchApi()
 * y retorna { status, data, meta } (estructura estándar del envelope API).
 */

import { mockRequest, MOCK_CONFIG } from '@/shared/utils/mock.utils';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

/**
 * Helper interno para peticiones al backend.
 * - Configura headers JSON + Accept application/json
 * - Lanza error si la respuesta no es OK
 * - Retorna el JSON parseado
 *
 * @param {string} endpoint - Ruta relativa (ej: '/auth/login')
 * @param {Object} options - Opciones de fetch (method, body, headers, etc.)
 * @returns {Promise<Object>} Respuesta JSON del backend
 */
async function fetchApi(endpoint, options = {}) {
  const response = await fetch(`${API_BASE}${endpoint}`, {
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...options.headers,
    },
    ...options,
  });

  const data = await response.json();

  if (!response.ok) {
    // Extraemos el mensaje de error del envelope estándar del backend
    // El backend devuelve: { status: "error", message: "...", errors: {...} }
    const errorMessage = data.message || `Error ${response.status}`;

    // Creamos un error con la estructura completa para que el hook
    // pueda mostrar errores de validación campo por campo si es necesario
    const error = new Error(errorMessage);
    error.status = response.status;
    error.errors = data.errors || {};
    throw error;
  }

  return data;
}

export const AuthService = {
  /**
   * Registra un nuevo usuario.
   *
   * ✅ Patrón aplicado: §15 (Servicios API)
   * ✅ snake_case en propiedades (refleja el backend)
   * ✅ await mockRequest(delay) al inicio
   * ✅ JSDoc completo
   *
   * @param {Object} credentials - Datos del registro
   * @param {string} credentials.username - Nombre de usuario único (handle @usuario)
   * @param {string} credentials.first_name - Nombre real
   * @param {string} credentials.last_name - Apellido real
   * @param {string} credentials.email - Correo electrónico
   * @param {string} credentials.password - Contraseña (mín 8 caracteres)
   * @param {string} credentials.password_confirmation - Confirmación de contraseña
   * @returns {Promise<Object>} { status: "success", data: { user, token, token_type } }
   */
  async register(credentials) {
    await mockRequest(MOCK_CONFIG.DELAYS.SLOW);
    return fetchApi('/auth/register', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
  },

  /**
   * Inicia sesión con email y contraseña.
   *
   * LoginRequest en backend espera: { email, password }
   * Devuelve: { user, token, token_type }
   *
   * @param {Object} credentials - Credenciales de login
   * @param {string} credentials.email - Correo electrónico
   * @param {string} credentials.password - Contraseña
   * @returns {Promise<Object>} { status: "success", data: { user, token, token_type } }
   */
  async login(credentials) {
    await mockRequest(MOCK_CONFIG.DELAYS.MEDIUM);
    return fetchApi('/auth/login', {
      method: 'POST',
      body: JSON.stringify(credentials),
    });
  },

  /**
   * Cierra la sesión del usuario (revoca el token actual).
   *
   * Necesita el token en el header Authorization porque es una ruta
   * protegida con middleware auth:sanctum.
   *
   * El backend elimina el token de la tabla personal_access_tokens.
   * Después de esto, el token ya no sirve para ninguna petición.
   *
   * @param {string} token - Token de acceso actual (Bearer)
   * @returns {Promise<Object>} { status: "success", data: null }
   */
  async logout(token) {
    await mockRequest(MOCK_CONFIG.DELAYS.FAST);
    return fetchApi('/auth/logout', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });
  },

  /**
   * Obtiene la URL de redirección para login social (Google, GitHub, Discord).
   *
   * ⚠️ Importante: No es un método asíncrono. Simplemente construye la URL
   * a la que el frontend redirigirá al usuario. El backend (SocialiteController@redirect)
   * se encarga de redirigir al proveedor OAuth correspondiente.
   *
   * @param {string} provider - 'google', 'github', 'discord'
   * @returns {string} URL completa para redirección del navegador
   */
  getSocialRedirectUrl(provider) {
    return `${API_BASE}/auth/social/${provider}`;
  },

  /**
   * Envía solicitud de restablecimiento de contraseña.
   *
   * El backend SIEMPRE devuelve success aunque el email no exista
   * (medida de seguridad para no revelar qué emails están registrados).
   *
   * @param {string} email - Correo electrónico registrado
   * @returns {Promise<Object>} { status: "success", message: string }
   */
  async forgotPassword(email) {
    await mockRequest(MOCK_CONFIG.DELAYS.SLOW);
    return fetchApi('/auth/forgot-password', {
      method: 'POST',
      body: JSON.stringify({ email }),
    });
  },

  /**
   * Restablece la contraseña con el token recibido por email.
   *
   * Después del reset, el backend autentica al usuario automáticamente
   * y devuelve un nuevo token (no hace falta hacer login después).
   *
   * @param {Object} data - Datos del reset
   * @param {string} data.email - Correo electrónico
   * @param {string} data.token - Token de reset (recibido por email)
   * @param {string} data.password - Nueva contraseña
   * @param {string} data.password_confirmation - Confirmación
   * @returns {Promise<Object>} { status: "success", data: { user, token, token_type } }
   */
  async resetPassword(data) {
    await mockRequest(MOCK_CONFIG.DELAYS.SLOW);
    return fetchApi('/auth/reset-password', {
      method: 'POST',
      body: JSON.stringify(data),
    });
  },

  /**
   * Obtiene las sesiones activas del usuario.
   *
   * Cada sesión es un token de Sanctum diferente. El campo is_current
   * indica si es la sesión actual (el token usado en esta petición).
   *
   * @param {string} token - Token de acceso actual
   * @returns {Promise<Object>} { status: "success", data: Array<Session> }
   */
  async getSessions(token) {
    await mockRequest(MOCK_CONFIG.DELAYS.MEDIUM);
    return fetchApi('/user/sessions', {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });
  },

  /**
   * Revoca (elimina) una sesión específica.
   *
   * Esto borra el token de la tabla personal_access_tokens.
   * El dispositivo que usaba ese token dejará de tener acceso inmediatamente.
   *
   * @param {string} token - Token de acceso actual (para autenticar la petición)
   * @param {string} sessionUuid - UUID de la sesión a revocar
   * @returns {Promise<Object>} { status: "success", data: null }
   */
  async revokeSession(token, sessionUuid) {
    await mockRequest(MOCK_CONFIG.DELAYS.MEDIUM);
    return fetchApi(`/user/sessions/${sessionUuid}`, {
      method: 'DELETE',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });
  },

  /**
   * Envía una nueva solicitud de verificación de email.
   *
   * El backend envía un nuevo correo con el link de verificación.
   * Tiene rate limiting (típicamente 1 vez por minuto) para evitar spam.
   *
   * @param {string} token - Token de acceso actual
   * @returns {Promise<Object>} { status: "success", message: string }
   */
  async sendEmailVerification(token) {
    await mockRequest(MOCK_CONFIG.DELAYS.SLOW);
    return fetchApi('/auth/email/verification-notification', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });
  },

  // ─── 2FA (Two-Factor Authentication) ───────────────────────────────
  // Estos métodos se implementarán cuando el backend tenga 2FA funcionando.
  // Por ahora están esbozados con el contrato esperado.

  /**
   * Activa 2FA para el usuario.
   * @param {string} token - Token de acceso actual
   * @returns {Promise<Object>} { status: "success", data: { qr_code_url, secret } }
   */
  async enable2FA(token) {
    await mockRequest(MOCK_CONFIG.DELAYS.SLOW);
    return fetchApi('/auth/2fa/enable', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
    });
  },

  /**
   * Confirma la activación de 2FA con el código TOTP.
   * @param {string} token - Token de acceso actual
   * @param {string} code - Código de 6 dígitos de la app autenticadora
   * @returns {Promise<Object>} { status: "success", data: { backup_codes: string[] } }
   */
  async confirm2FA(token, code) {
    await mockRequest(MOCK_CONFIG.DELAYS.MEDIUM);
    return fetchApi('/auth/2fa/confirm', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ code }),
    });
  },

  /**
   * Desactiva 2FA (requiere password y código TOTP por seguridad).
   * @param {string} token - Token de acceso actual
   * @param {string} password - Contraseña actual del usuario
   * @param {string} code - Código TOTP de 6 dígitos
   * @returns {Promise<Object>} { status: "success", data: null }
   */
  async disable2FA(token, password, code) {
    await mockRequest(MOCK_CONFIG.DELAYS.MEDIUM);
    return fetchApi('/auth/2fa/disable', {
      method: 'POST',
      headers: { 'Authorization': `Bearer ${token}` },
      body: JSON.stringify({ password, code }),
    });
  },
};
```

### Explicación línea por línea

| Línea | Explicación |
|:---|:---|
| `mockRequest(MOCK_CONFIG.DELAYS.SLOW)` | Simula latencia de red. En desarrollo, esto evita que el frontend se sienta "instantáneo" y permite probar estados de carga. Se reemplazará por latencia real cuando el backend esté conectado. |
| `fetchApi(...)` | Helper centralizado: evita repetir headers y lógica de error en cada método. |
| `const data = await response.json()` | Parseamos la respuesta **antes** de verificar `response.ok` porque el backend envía errores con body JSON. |
| `error.errors = data.errors \|\| {}` | Adjuntamos los errores de validación del backend (422) al objeto Error para que el hook pueda mostrarlos en la UI. |
| `'Authorization': \`Bearer ${token}\`` | Los endpoints protegidos con `auth:sanctum` necesitan este header. Sin él, el backend responde 401. |

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §15 Servicios API | ✅ Export como objeto `export const AuthService = { ... }` |
| §15 JSDoc | ✅ `@service` en archivo, `@param`/`@returns` en cada método |
| §15 mockRequest | ✅ `await mockRequest(delay)` al inicio de cada método |
| §15 snake_case | ✅ Propiedades en snake_case (refleja el backend) |
| §15 Estructura retorno | ✅ `{ status, data, meta }` envelope estándar |

---

## 4. Paso 2: Crear useAuthStore.js (Zustand)

**Archivo:** `src/shared/stores/useAuthStore.js`

### ¿Qué hace este archivo?

Almacena el **token de acceso** y el **usuario autenticado** en memoria y los **persiste** en localStorage. Esto permite que al recargar la página, el usuario no tenga que volver a iniciar sesión.

### Diferencia clave con otros stores

A diferencia de `useReplyStore` (que es temporal en memoria), `useAuthStore` **necesita persistencia** porque:
- El token debe sobrevivir a recargas de página
- La sesión debe mantenerse aunque el usuario cierre el navegador y vuelva

### Código completo

```javascript
/**
 * @store useAuthStore
 * @description Almacena el token de acceso y el usuario autenticado.
 *
 * Utiliza persist middleware de Zustand para mantener la sesión entre
 * recargas de página. Los datos se guardan en localStorage bajo la clave 'auth-storage'.
 *
 * ✅ Patrón aplicado: §16 (Componentes UI / Stores)
 * ✅ persist + partialize para excluir estado transitorio
 * ✅ Naming: useAuthStore.js (useCamelCaseStore.js)
 */

import { create } from 'zustand';
import { persist } from 'zustand/middleware';

/**
 * Creamos el store con persistencia.
 *
 * persist() es un middleware de Zustand que automáticamente:
 * 1. Guarda el estado en localStorage cada vez que cambia
 * 2. Restaura el estado al cargar la página
 * 3. Permite controlar QUÉ se persiste con partialize
 */
export const useAuthStore = create(
  persist(
    /**
     * set es la función que Zustand provee para actualizar el estado.
     * get (opcional) permitiría leer el estado actual.
     */
    (set) => ({
      // ─── Estado inicial ───────────────────────────────────────
      user: null,          // Objeto del usuario autenticado (o null)
      token: null,         // Bearer token de Sanctum (o null)
      requires_2fa: false, // Flag para flujo de 2FA parcialmente autenticado
      isAuthenticated: false, // Cálculo derivado: ¿hay sesión activa?

      // ─── Acciones ─────────────────────────────────────────────

      /**
       * Establece los datos de autenticación después de login/register.
       *
       * @param {Object} user - Objeto usuario del backend
       * @param {string} token - Bearer token de Sanctum
       */
      setAuth: (user, token) => set({
        user,
        token,
        isAuthenticated: true,
        requires_2fa: false,
      }),

      /**
       * Marca que el usuario necesita completar 2FA.
       * Se usa cuando el login detecta que el usuario tiene 2FA activado.
       * En ese caso, el token es "parcial" y necesita un segundo factor.
       *
       * @param {boolean} value - true si requiere 2FA
       */
      setRequires2FA: (value) => set({
        requires_2fa: value,
      }),

      /**
       * Limpia toda la autenticación (logout).
       * Resetea todo al estado inicial.
       */
      clearAuth: () => set({
        user: null,
        token: null,
        isAuthenticated: false,
        requires_2fa: false,
      }),

      /**
       * Actualiza los datos del usuario sin borrar el token.
       * Se usa después de que el usuario edita su perfil.
       *
       * @param {Object} user - Nuevos datos del usuario
       */
      updateUser: (user) => set({ user }),
    }),
    {
      /**
       * Nombre de la clave en localStorage.
       * Así es como Zustand sabe dónde guardar/recuperar los datos.
       */
      name: 'auth-storage',

      /**
       * partialize controla QUÉ campos del store se persisten en localStorage.
       *
       * ¿Por qué es importante?
       * - Si en el futuro agregamos campos temporales (como `isLoading`),
       *   NO queremos que se persistan.
       * - Solo persistimos lo necesario para restaurar la sesión.
       *
       * En nuestro caso, persistimos TODO porque todos los campos
       * son parte del estado de sesión. Pero lo hacemos explícito
       * para que cualquier campo futuro sea añadido aquí a propósito.
       */
      partialize: (state) => ({
        user: state.user,
        token: state.token,
        isAuthenticated: state.isAuthenticated,
        requires_2fa: state.requires_2fa,
      }),
    }
  )
);
```

### Explicación conceptual: ¿Cómo funciona Zustand + persist?

```
                    ┌──────────────────────────────────────┐
                    │          useAuthStore                 │
                    │                                      │
                    │   En memoria (Reactiva)               │
                    │   ┌─────────────────────────────┐     │
                    │   │  user: {...}                │     │
                    │   │  token: "1|abc123..."       │     │
                    │   │  isAuthenticated: true       │     │
                    │   └─────────────────────────────┘     │
                    │            │                           │
                    │   persist() middleware                │
                    │            │                           │
                    │            ▼                           │
                    │   Sincroniza automáticamente           │
                    └───────────┼───────────────────────────┘
                                │
                    ┌───────────▼───────────────────────────┐
                    │       localStorage                    │
                    │   Key: "auth-storage"                 │
                    │   Value: JSON.stringify({...})        │
                    └───────────────────────────────────────┘
```

Cuando el usuario:
- **Inicia sesión**: `setAuth(user, token)` → Zustand actualiza estado en memoria → `persist` guarda en localStorage
- **Recarga la página**: `persist` lee de localStorage → restaura el estado → `isAuthenticated: true`
- **Cierra sesión**: `clearAuth()` → Zustand resetea estado → `persist` guarda null en localStorage

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §16 Zustand con persist | ✅ `create(persist(...))` |
| §16 partialize | ✅ `partialize: (state) => ({ ... })` excluye estado transitorio |
| §16 Naming | ✅ `useAuthStore.js` (useCamelCaseStore.js) |

---

## 5. Paso 3: Crear useAuth.js (Hooks React Query)

**Archivo:** `src/shared/hooks/useAuth.js`

### ¿Qué hace este archivo?

Centraliza TODA la lógica de autenticación usando React Query para mutaciones. Cada hook exporta una función que retorna un objeto `useMutation` listo para usar en los componentes.

### ¿Por qué React Query en lugar de llamar al servicio directamente?

React Query aporta:
1. **Manejo de estados**: `isPending`, `isError`, `isSuccess` automáticos
2. **Deduplicación**: Si dos componentes llaman a la misma mutación, no se duplica
3. **Cache**: Para queries, pero para mutations también da estructura consistente
4. **Retry**: Reintento automático en caso de error de red

### Código completo

```javascript
/**
 * @file useAuth.js
 * @description Hooks de autenticación usando React Query y Zustand.
 *
 * Cada hook retorna un objeto useMutation listo para usar en componentes.
 * Siguen el Protocolo Vyne (§12: Mutaciones) con:
 *   - mutationFn → llama al service
 *   - onSuccess → setAuth + toast + redirect
 *   - onError → toast con mensaje de error
 *
 * ✅ Patrón aplicado: §12 (Mutaciones — Protocolo VYNE)
 * ✅ Patrón aplicado: §17 (Naming: useLogin, useRegister, useLogout)
 *
 * @example
 * // En un componente:
 * const loginMutation = useLogin();
 * loginMutation.mutate({ email, password });
 */

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'next/navigation';
import { toast } from 'sonner';
import { AuthService } from '@/services/auth.service';
import { useAuthStore } from '@/shared/stores/useAuthStore';

// ═══════════════════════════════════════════════════════════════════
//  HOOKS DE AUTENTICACIÓN (Mutaciones)
// ═══════════════════════════════════════════════════════════════════

/**
 * Hook para iniciar sesión.
 *
 * Flujo:
 * 1. El componente llama `loginMutation.mutate({ email, password })`
 * 2. React Query ejecuta `AuthService.login(credentials)`
 * 3. Si éxito: guarda user+token en Zustand, muestra toast, redirige a /
 * 4. Si error: muestra toast con el mensaje del backend
 *
 * Caso especial: 2FA
 * Si el backend responde con `requires_2fa: true`, significa que el
 * usuario tiene 2FA activado. En ese caso:
 * - NO guardamos el token como definitivo (es parcial)
 * - Marcamos requires_2fa en el store
 * - Redirigimos a /auth/2fa para completar el segundo factor
 *
 * @returns {import('@tanstack/react-query').UseMutationResult}
 */
export function useLogin() {
  const router = useRouter();
  const setAuth = useAuthStore((state) => state.setAuth);
  const setRequires2FA = useAuthStore((state) => state.setRequires2FA);

  return useMutation({
    /**
     * mutationFn: La función que ejecuta la mutación.
     * Recibe los parámetros que se pasan a mutate().
     * Debe retornar una promesa.
     */
    mutationFn: (credentials) => AuthService.login(credentials),

    /**
     * onSuccess: Se ejecuta cuando la mutación termina sin errores.
     * response es lo que retornó mutationFn (el JSON del backend).
     *
     * Estructura esperada de response:
     * {
     *   status: "success",
     *   data: {
     *     user: { uuid, username, ... },
     *     token: "1|abc...",
     *     requires_2fa: false
     *   }
     * }
     */
    onSuccess: (response) => {
      const { user, token, requires_2fa } = response.data;

      if (requires_2fa) {
        // ─── Flujo 2FA ─────────────────────────────────────
        // El usuario tiene 2FA activado. Guardamos un estado
        // "parcial" y redirigimos a la página de verificación 2FA.
        setRequires2FA(true);
        router.push('/auth/2fa');
        return;
      }

      // ─── Flujo normal ────────────────────────────────────
      setAuth(user, token);
      toast.success('¡Bienvenido de vuelta!');
      router.push('/');
    },

    /**
     * onError: Se ejecuta si mutationFn lanza un error.
     * error es el objeto Error que lanzamos en fetchApi().
     *
     * Mostramos errores de campo específico si existen
     * (errores 422 del backend con { errors: { field: [msg] } }).
     */
    onError: (error) => {
      // Si hay errores de validación por campo, mostramos el primero
      const fieldErrors = error.errors;
      const firstError = fieldErrors && Object.values(fieldErrors)[0]?.[0];

      toast.error('Error al iniciar sesión', {
        description: firstError || error.message,
      });
    },
  });
}

/**
 * Hook para registrarse.
 *
 * Flujo:
 * 1. El componente llama `registerMutation.mutate({ username, first_name, last_name, email, password, password_confirmation })`
 * 2. React Query ejecuta `AuthService.register(userData)`
 * 3. Si éxito: guarda user+token en Zustand, muestra toast, redirige a /
 * 4. Si error: muestra toast con errores de validación del backend
 *
 * ⚠️ Importante: El backend devuelve 201 (Created) en registro exitoso.
 * No necesitamos client_uuid aquí porque el backend usa StoreUserRequest
 * que no tiene client_uuid (ver árbol de decisión en api_contract.md).
 *
 * @returns {import('@tanstack/react-query').UseMutationResult}
 */
export function useRegister() {
  const router = useRouter();
  const setAuth = useAuthStore((state) => state.setAuth);

  return useMutation({
    mutationFn: (userData) => AuthService.register(userData),

    onSuccess: (response) => {
      const { user, token } = response.data;
      setAuth(user, token);
      toast.success('¡Cuenta creada con éxito!');
      router.push('/');
    },

    onError: (error) => {
      const fieldErrors = error.errors;
      const firstError = fieldErrors && Object.values(fieldErrors)[0]?.[0];

      toast.error('Error al registrarse', {
        description: firstError || error.message,
      });
    },
  });
}

/**
 * Hook para cerrar sesión.
 *
 * Flujo:
 * 1. Ejecuta logout en el backend (revoca el token actual)
 * 2. Limpia el store de autenticación
 * 3. Invalida queries relacionadas al usuario
 * 4. Redirige a /login
 *
 * Comportamiento defensivo:
 * - Si la llamada al backend falla (error de red), IGUAL forzamos
 *   el logout local. No queremos dejar al usuario atrapado sin
 *   poder cerrar sesión porque el servidor no responde.
 *
 * @returns {import('@tanstack/react-query').UseMutationResult}
 */
export function useLogout() {
  const router = useRouter();
  const queryClient = useQueryClient();
  const token = useAuthStore((state) => state.token);
  const clearAuth = useAuthStore((state) => state.clearAuth);

  return useMutation({
    mutationFn: () => AuthService.logout(token),

    /**
     * Siempre limpiamos la auth, incluso si la petición falla.
     * Por eso usamos onSettled en lugar de onSuccess.
     */
    onSettled: () => {
      // 1. Limpiamos el store de auth (Zustand)
      clearAuth();

      // 2. Limpiamos la caché de React Query para datos del usuario
      //    Esto evita que datos antiguos aparezcan si otro usuario inicia sesión
      queryClient.removeQueries({ queryKey: ['current_user_v2'] });
      queryClient.removeQueries({ queryKey: ['user'] });
      queryClient.removeQueries({ queryKey: ['user_sessions'] });

      // 3. Redirigimos al login
      router.push('/login');
    },

    onError: (error) => {
      // Aún así mostramos el error, pero el logout local ya ocurrió
      console.error('Error al cerrar sesión en el servidor:', error);
    },
  });
}

/**
 * Hook para verificar el código 2FA.
 *
 * Se usa en la página /auth/2fa después de que el usuario
 * ingresa el código de 6 dígitos de su app autenticadora.
 *
 * El backend espera un token "parcial" (el que se guardó durante
 * el login con requires_2fa=true) y el código TOTP.
 *
 * @returns {import('@tanstack/react-query').UseMutationResult}
 */
export function useVerify2FA() {
  const router = useRouter();
  const token = useAuthStore((state) => state.token);
  const setAuth = useAuthStore((state) => state.setAuth);
  const setRequires2FA = useAuthStore((state) => state.setRequires2FA);

  return useMutation({
    /**
     * El backend espera:
     *   POST /auth/2fa/verify
     *   Body: { code: "123456" }
     *   Headers: Authorization: Bearer {token_parcial}
     *
     * Y devuelve:
     *   { status: "success", data: { user, token: "nuevo_token_definitivo" } }
     */
    mutationFn: (code) => AuthService.verify2FA(token, code),

    onSuccess: (response) => {
      const { user, token: newToken } = response.data;
      // Reemplazamos el token parcial por el definitivo
      setAuth(user, newToken);
      setRequires2FA(false);
      toast.success('Verificación exitosa');
      router.push('/');
    },

    onError: (error) => {
      toast.error('Código inválido', {
        description: error.message,
      });
    },
  });
}

// ═══════════════════════════════════════════════════════════════════
//  HOOKS DE SESIONES (Query + Mutation)
// ═══════════════════════════════════════════════════════════════════

/**
 * Hook para obtener y gestionar sesiones activas.
 *
 * Retorna:
 * - sessions: Array de sesiones activas
 * - isLoading: Estado de carga
 * - revokeSession: Función para cerrar una sesión específica
 * - revokeAllSessions: Función para cerrar todas las sesiones excepto la actual
 *
 * ✅ Patrón aplicado: §11 (React Query Lecturas)
 * ✅ queryKey: ['user_sessions'] con enabled: !!token
 *
 * @returns {Object} { sessions, isLoading, revokeSession, revokeAllSessions }
 */
export function useSessions() {
  const token = useAuthStore((state) => state.token);
  const queryClient = useQueryClient();

  /**
   * Query para obtener sesiones.
   * Solo se ejecuta si hay token (enabled: !!token).
   */
  const sessionsQuery = useQuery({
    queryKey: ['user_sessions'],
    queryFn: async () => {
      const response = await AuthService.getSessions(token);
      return response.data;
    },
    enabled: !!token,
    staleTime: 1000 * 60 * 2, // 2 minutos — las sesiones cambian poco
  });

  /**
   * Mutación para revocar una sesión específica.
   */
  const revokeMutation = useMutation({
    mutationFn: (sessionUuid) => AuthService.revokeSession(token, sessionUuid),

    onSuccess: () => {
      toast.success('Sesión cerrada en ese dispositivo');
      // Refrescamos la lista de sesiones
      queryClient.invalidateQueries({ queryKey: ['user_sessions'] });
    },

    onError: (error) => {
      toast.error('Error al cerrar sesión', {
        description: error.message,
      });
    },
  });

  return {
    sessions: sessionsQuery.data || [],
    isLoading: sessionsQuery.isLoading,
    revokeSession: revokeMutation.mutate,
    isRevoking: revokeMutation.isPending,
  };
}

/**
 * Hook para reenviar el correo de verificación de email.
 *
 * @returns {import('@tanstack/react-query').UseMutationResult}
 */
export function useResendVerification() {
  const token = useAuthStore((state) => state.token);

  return useMutation({
    mutationFn: () => AuthService.sendEmailVerification(token),

    onSuccess: () => {
      toast.success('Correo de verificación enviado');
    },

    onError: (error) => {
      toast.error('Error al enviar verificación', {
        description: error.message,
      });
    },
  });
}
```

### Explicación del flujo de mutación

```
Componente                    useAuth.js                      AuthService               Backend
    │                            │                              │                        │
    │  mutate({email, pass})     │                              │                        │
    ├──────────────────────────► │                              │                        │
    │                            │  mutationFn ejecuta          │                        │
    │                            ├─────────────────────────────►│                        │
    │                            │                              │  fetchApi()            │
    │                            │                              ├───────────────────────►│
    │                            │                              │                        │
    │                            │  ◄─────── response ──────────┤                        │
    │                            │                              │                        │
    │                            │                              │                        │
    │                            │  onSuccess(response)         │                        │
    │                            │  ├── setAuth(user, token)    │                        │
    │                            │  ├── toast.success()         │                        │
    │                            │  └── router.push('/')        │                        │
    │◄───────────────────────────┤                              │                        │
    │  (UI se actualiza)         │                              │                        │
```

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §12 useMutation | ✅ `useMutation({ mutationFn, onSuccess, onError })` |
| §12 Sonner toast | ✅ `toast.success()`, `toast.error()` |
| §12 mutationFn parámetros | ✅ `mutationFn: (credentials) => AuthService.login(credentials)` |
| §11 useQuery | ✅ `useQuery({ queryKey, queryFn, enabled, staleTime })` |
| §17 Naming | ✅ `useLogin`, `useRegister`, `useLogout` (camelCase) |

---

## 6. Paso 4: Modificar LoginForm.jsx

**Archivo:** `src/features/auth/components/organisms/LoginForm.jsx`

### ¿Qué cambia?

El formulario actual tiene un mock que simula 1.5s de espera y redirige. Vamos a:

1. **Reemplazar el mock** por la mutación `useLogin`
2. **Detectar 2FA** → redirigir a `/auth/2fa` si es necesario
3. **Conectar botón Google** → redirigir a Socialite
4. **Conectar "¿La olvidaste?"** → redirigir a `/auth/forgot-password`

### Código completo modificado

```javascript
"use client";

import React, { useState } from "react";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";
import Button from "@/shared/components/ui/atoms/Button";
import { FiMail, FiLock, FiArrowRight, FiEye, FiEyeOff } from "react-icons/fi";
import { useLogin } from "@/shared/hooks/useAuth";
import { AuthService } from "@/services/auth.service";

/**
 * @component LoginForm
 * @description Formulario de inicio de sesión conectado a la API real.
 *
 * ✅ Patrón aplicado: §2 (SRP — el componente solo orquesta UI)
 * ✅ Patrón aplicado: §12 (useMutation para login)
 */
export default function LoginForm() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);

  // ─── Hook de autenticación ──────────────────────────────────
  // useLogin retorna un objeto useMutation con:
  //   mutate: función para ejecutar la mutación
  //   isPending: true mientras la petición está en curso
  //   isError: true si la petición falló
  const loginMutation = useLogin();

  /**
   * Maneja el envío del formulario.
   *
   * ✅ Antes (mock):
   *    await new Promise((resolve) => setTimeout(resolve, 1500));
   *
   * ✅ Ahora (real):
   *    loginMutation.mutate({ email, password });
   *    React Query maneja el loading, success y error.
   */
  const handleSubmit = async (e) => {
    e.preventDefault();

    loginMutation.mutate(
      { email, password },
      {
        /**
         * onSuccess callback local (opcional).
         * El hook useLogin ya tiene onSuccess global que redirige a /.
         * Pero aquí podemos agregar lógica adicional si es necesario.
         *
         * En este caso, no necesitamos nada extra porque el hook
         * ya maneja el toast y la redirección.
         */
        onSuccess: (response) => {
          // Si el backend indica que requiere 2FA, redirigimos
          if (response.data?.requires_2fa) {
            router.push("/auth/2fa");
          }
        },
      }
    );
  };

  /**
   * Redirige al usuario al flujo de login social (Google).
   *
   * ⚠️ Importante: Esto NO es una petición fetch. Es una redirección
   * del navegador a la URL de Socialite. El backend redirigirá a
   * Google, y Google redirigirá de vuelta a /auth/callback?token=xxx.
   *
   * Como el navegador abandona la página, no podemos usar React Router.
   * Usamos window.location.href para la redirección completa.
   */
  const handleGoogleLogin = () => {
    window.location.href = AuthService.getSocialRedirectUrl("google");
  };

  /**
   * Redirige a la página de recuperación de contraseña.
   * (Se creará en un paso futuro, por ahora redirigimos a una ruta
   * que mostrará 404 hasta que se implemente).
   */
  const handleForgotPassword = () => {
    router.push("/auth/forgot-password");
  };

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
      <div className="text-center mb-2">
        <h1 className="text-3xl font-black text-forest-light tracking-tight mb-2">
          ¡Hola de nuevo!
        </h1>
        <p className="text-forest-muted text-sm font-medium">
          Es un placer verte otra vez.
        </p>
      </div>

      {/* Social Login */}
      <div className="flex flex-col gap-3">
        <button
          type="button"
          onClick={handleGoogleLogin}
          className="w-full h-12 bg-white hover:bg-gray-100 text-gray-900 font-bold rounded-2xl transition-all flex items-center justify-center gap-3 shadow-lg"
        >
          <svg className="w-5 h-5" viewBox="0 0 24 24">
            <path
              fill="#4285F4"
              d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
            />
            <path
              fill="#34A853"
              d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
            />
            <path
              fill="#FBBC05"
              d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
            />
            <path
              fill="#EA4335"
              d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
            />
          </svg>
          Continuar con Google
        </button>
      </div>

      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-forest-border/50"></div>
        </div>
        <div className="relative flex justify-center text-[10px] uppercase tracking-widest font-black">
          <span className="bg-forest-card px-4 text-forest-muted/60">O usa tu correo</span>
        </div>
      </div>

      <div className="flex flex-col gap-4">
        {/* Email Input */}
        <div className="flex flex-col gap-1.5">
          <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60 px-1">
            Correo Electrónico
          </label>
          <div className="group relative">
            <FiMail className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
            <input
              type="email"
              required
              name="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="nombre@ejemplo.com"
              className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-4 py-4 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60 focus:shadow-[0_0_20px_rgba(34,197,94,0.05)]"
            />
          </div>
        </div>

        {/* Password Input */}
        <div className="flex flex-col gap-1.5">
          <div className="flex justify-between items-center px-1">
            <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60">
              Contraseña
            </label>
            <button
              type="button"
              onClick={handleForgotPassword}
              className="text-[10px] font-bold text-forest-accent hover:text-forest-light transition-colors uppercase tracking-wider"
            >
              ¿La olvidaste?
            </button>
          </div>
          <div className="group relative">
            <FiLock className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
            <input
              type={showPassword ? "text" : "password"}
              required
              name="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
              className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-12 py-4 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60 focus:shadow-[0_0_20px_rgba(34,197,94,0.05)]"
            />
            <button
              type="button"
              onClick={() => setShowPassword(!showPassword)}
              className="absolute right-4 top-1/2 -translate-y-1/2 text-forest-muted hover:text-forest-light transition-colors"
            >
              {showPassword ? <FiEyeOff size={18} /> : <FiEye size={18} />}
            </button>
          </div>
        </div>
      </div>

      <div className="mt-2">
        <Button
          type="submit"
          // Deshabilitamos el botón mientras la mutación está en curso
          // isPending es provisto por React Query automáticamente
          disabled={loginMutation.isPending}
          className="w-full h-14 bg-forest-accent hover:bg-forest-light text-forest-dark font-black rounded-2xl transition-all shadow-[0_10px_30px_rgba(34,197,94,0.15)] hover:shadow-[0_15px_40px_rgba(34,197,94,0.25)] flex items-center justify-center gap-2 group"
        >
          {loginMutation.isPending ? (
            <motion.div
              animate={{ rotate: 360 }}
              transition={{ repeat: Infinity, duration: 1, ease: "linear" }}
              className="w-5 h-5 border-2 border-forest-dark border-t-transparent rounded-full"
            />
          ) : (
            <>
              Iniciar Sesión
              <FiArrowRight className="transition-transform group-hover:translate-x-1" />
            </>
          )}
        </Button>
      </div>

      <div className="text-center mt-2">
        <p className="text-forest-muted text-xs font-medium">
          ¿No tienes una cuenta?{" "}
          <button
            type="button"
            onClick={() => router.push("/register")}
            className="text-forest-accent font-black hover:text-forest-light transition-colors underline underline-offset-4 decoration-forest-accent/30"
          >
            Regístrate ahora
          </button>
        </p>
      </div>
    </form>
  );
}
```

### ¿Qué cambió exactamente?

| Antes (mock) | Después (real) |
|:---|:---|
| `const [isLoading, setIsLoading] = useState(false)` | Eliminado — React Query provee `isPending` |
| `handleSubmit` con `setTimeout` + `router.push("/")` | `handleSubmit` llama a `loginMutation.mutate({ email, password })` |
| Botón Google sin `onClick` | Botón Google con `handleGoogleLogin` → `window.location.href` |
| Botón "¿La olvidaste?" sin `onClick` | Botón con `handleForgotPassword` → `router.push("/auth/forgot-password")` |
| `disabled={isLoading}` | `disabled={loginMutation.isPending}` |
| `{isLoading ? (...)` spiner | `{loginMutation.isPending ? (...)` spiner |

### Concepto clave: ¿Por qué quitamos el useState para isLoading?

Antes se manejaba manualmente: `setIsLoading(true)` → setTimeout → `setIsLoading(false)`. Con React Query, el estado `isPending` es automático: es `true` desde que llamas a `mutate()` hasta que la promesa se resuelve (éxito o error). Esto elimina bugs comunes como olvidar resetear el estado en caso de error.

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §2 SRP Controllers (Frontend) | ✅ Componente solo orquesta UI, no tiene lógica de negocio |
| §12 Mutaciones Protocolo VYNE | ✅ `useLogin().mutate()` con manejo de estados |
| §16 "use client" | ✅ Ya tenía "use client" (hooks de estado y evento) |

---

## 7. Paso 5: Modificar RegisterForm.jsx

**Archivo:** `src/features/auth/components/organisms/RegisterForm.jsx`

### ¿Qué cambia?

1. **Cambio crítico**: El campo "name" se reemplaza por 4 campos: `username`, `first_name`, `last_name`, `email`, `password`
2. **Reemplazar mock** por la mutación `useRegister`
3. **Conectar botón Google** → redirigir a Socialite
4. **Username único**: Vyntra es Discord moderno: solo `username` único.

### ¿Por qué este cambio?

El contrato API del backend (`StoreUserRequest`) espera:

```json
{
  "username": "santi_dev",
  "first_name": "Santiago",
  "last_name": "Mejias",
  "email": "santi@example.com",
  "password": "contraseña123",
  "password_confirmation": "contraseña123"
}
```

El formulario actual solo tiene un campo "name". Necesitamos desglosarlo en los 4 campos que el backend espera.

### Código completo modificado

```javascript
"use client";

import React, { useState } from "react";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";
import Button from "@/shared/components/ui/atoms/Button";
import { FiMail, FiLock, FiUser, FiArrowRight, FiEye, FiEyeOff, FiAtSign } from "react-icons/fi";
import { useRegister } from "@/shared/hooks/useAuth";
import { AuthService } from "@/services/auth.service";

/**
 * @component RegisterForm
 * @description Formulario de registro conectado a la API real.
 *
 * ✅ Patrón aplicado: §2 (SRP — el componente solo orquesta UI)
 * ✅ Patrón aplicado: §12 (useMutation para register)
 */
export default function RegisterForm() {
  const router = useRouter();
  const [username, setUsername] = useState("");
  const [userTag, setUserTag] = useState("");
  const [firstName, setFirstName] = useState("");
  const [lastName, setLastName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [showPassword, setShowPassword] = useState(false);

  // ─── Hook de autenticación ──────────────────────────────────
  const registerMutation = useRegister();

  /**
   * Maneja el envío del formulario.
   *
   * Construye el payload exacto que espera el backend StoreUserRequest:
   * { username, first_name, last_name, email, password, password_confirmation }
   */
  const handleSubmit = async (e) => {
    e.preventDefault();

    registerMutation.mutate({
      username,
      first_name: firstName,
      last_name: lastName,
      email,
      password,
      password_confirmation: passwordConfirmation,
    });
  };

  /**
   * Redirige al usuario al flujo de registro social (Google).
   * Misma URL que el login — Socialite maneja ambos casos.
   */
  const handleGoogleRegister = () => {
    window.location.href = AuthService.getSocialRedirectUrl("google");
  };

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-5">
      <div className="text-center mb-2">
        <h1 className="text-3xl font-black text-forest-light tracking-tight mb-2">
          Crea tu cuenta
        </h1>
        <p className="text-forest-muted text-sm font-medium">
          Únete a la comunidad de Vyntra.
        </p>
      </div>

      {/* Social Register */}
      <div className="flex flex-col gap-3">
        <button
          type="button"
          onClick={handleGoogleRegister}
          className="w-full h-12 bg-white hover:bg-gray-100 text-gray-900 font-bold rounded-2xl transition-all flex items-center justify-center gap-3 shadow-lg"
        >
          <svg className="w-5 h-5" viewBox="0 0 24 24">
            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" />
            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
          </svg>
          Registrarse con Google
        </button>
      </div>

      <div className="relative">
        <div className="absolute inset-0 flex items-center">
          <div className="w-full border-t border-forest-border/50"></div>
        </div>
        <div className="relative flex justify-center text-[10px] uppercase tracking-widest font-black">
          <span className="bg-forest-card px-4 text-forest-muted/60">O completa tus datos</span>
        </div>
      </div>

      <div className="flex flex-col gap-3.5">
        {/* ─── Campo: Username ─────────────────────────────────────
             Vyntra es Discord moderno: solo username único.
             Ejemplo: "santi_dev"
        */}
        <div className="grid grid-cols-1 gap-3.5">
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60 px-1">
              Nombre de Usuario (@usuario)
            </label>
            <div className="group relative">
              <FiUser className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
              <input
                type="text"
                required
                name="username"
                value={username}
                onChange={(e) => setUsername(e.target.value)}
                placeholder="santi_dev"
                className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-4 py-3.5 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60"
              />
            </div>
          </div>
        </div>

        {/* ─── Fila: Nombre + Apellido ────────────────────────────── */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60 px-1">
              Nombre
            </label>
            <div className="group relative">
              <FiUser className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
              <input
                type="text"
                required
                name="first_name"
                value={firstName}
                onChange={(e) => setFirstName(e.target.value)}
                placeholder="Santiago"
                className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-4 py-3.5 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60"
              />
            </div>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60 px-1">
              Apellido
            </label>
            <div className="group relative">
              <FiUser className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
              <input
                type="text"
                required
                name="last_name"
                value={lastName}
                onChange={(e) => setLastName(e.target.value)}
                placeholder="Mejias"
                className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-4 py-3.5 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60"
              />
            </div>
          </div>
        </div>

        {/* Email Input (sin cambios) */}
        <div className="flex flex-col gap-1.5">
          <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60 px-1">
            Correo Electrónico
          </label>
          <div className="group relative">
            <FiMail className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
            <input
              type="email"
              required
              name="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="nombre@ejemplo.com"
              className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-4 py-3.5 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60"
            />
          </div>
        </div>

        {/* Password Group (sin cambios estructurales) */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60 px-1">
              Contraseña
            </label>
            <div className="group relative">
              <FiLock className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
              <input
                type={showPassword ? "text" : "password"}
                required
                name="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-4 py-3.5 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60"
              />
            </div>
          </div>
          <div className="flex flex-col gap-1.5">
            <label className="text-[10px] font-black uppercase tracking-[0.2em] text-forest-muted/60 px-1">
              Confirmar
            </label>
            <div className="group relative">
              <FiLock className="absolute left-4 top-1/2 -translate-y-1/2 text-forest-muted transition-colors group-focus-within:text-forest-accent" size={18} />
              <input
                type={showPassword ? "text" : "password"}
                required
                name="password_confirmation"
                value={passwordConfirmation}
                onChange={(e) => setPasswordConfirmation(e.target.value)}
                placeholder="••••••••"
                className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl pl-12 pr-4 py-3.5 text-forest-light placeholder-forest-muted/30 outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/60"
              />
            </div>
          </div>
        </div>
      </div>

      <div className="mt-2">
        <Button
          type="submit"
          disabled={registerMutation.isPending}
          className="w-full h-14 bg-forest-accent hover:bg-forest-light text-forest-dark font-black rounded-2xl transition-all shadow-[0_10px_30px_rgba(34,197,94,0.15)] flex items-center justify-center gap-2 group"
        >
          {registerMutation.isPending ? (
            <motion.div animate={{ rotate: 360 }} transition={{ repeat: Infinity, duration: 1, ease: "linear" }} className="w-5 h-5 border-2 border-forest-dark border-t-transparent rounded-full" />
          ) : (
            <>
              Empezar ahora
              <FiArrowRight className="transition-transform group-hover:translate-x-1" />
            </>
          )}
        </Button>
      </div>

      <div className="text-center mt-2">
        <p className="text-forest-muted text-xs font-medium">
          ¿Ya tienes una cuenta?{" "}
          <button
            type="button"
            onClick={() => router.push("/login")}
            className="text-forest-accent font-black hover:text-forest-light transition-colors underline underline-offset-4 decoration-forest-accent/30"
          >
            Inicia sesión
          </button>
        </p>
      </div>
    </form>
  );
}
```

### Resumen de cambios en RegisterForm

| Aspecto | Antes (mock) | Después (real) |
|:---|:---|:---|
| Campos | 1 campo "name" | 3 campos: username, first_name, last_name |
| Íconos | FiUser para name | FiUser para username (NO hay tag) |
| Layout | 1 columna | 1 columna con 3 inputs en grid responsive |
| Submit | setTimeout mock | `registerMutation.mutate(payload)` |
| isLoading | `useState` manual | `registerMutation.isPending` (React Query) |
| Google | Sin onClick | `handleGoogleRegister` con `window.location.href` |

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §2 SRP | ✅ Componente solo orquesta UI, la lógica de negocio está en el hook |
| §12 Protocolo VYNE | ✅ `useRegister().mutate()` con manejo de estados |
| §15 snake_case | ✅ Payload usa snake_case: `username`, `first_name`, `last_name`, `email`, `password`, `password_confirmation` |

---

## 8. Paso 6: Crear página /auth/callback (Socialite)

**Archivo:** `src/app/auth/callback/page.js`

### ¿Qué hace esta página?

Cuando un usuario se autentica con Google (o cualquier proveedor social), el backend redirige al navegador a:

```
FRONTEND_URL/auth/callback?token=1|abc123...
```

Esta página:
1. **Lee el token** de la URL (query param `?token=`)
2. **Guarda el token** en el store de autenticación
3. **Obtiene los datos del usuario** (fetch a GET /api/user)
4. **Verifica `profile_completed`**: si el user viene de OAuth, es `false` y redirige a `/auth/complete-profile`. Si es `true`, redirige al dashboard.
5. **Redirige al destino apropiado** (dashboard O /auth/complete-profile)

> **¿Por qué este paso intermedio?** El backend crea el user tras OAuth con `username = null` (porque el backend no debe auto-generar usernames). El frontend lo sabe porque `AuthResource.profile_completed = false`. El user elige su username en `/auth/complete-profile` mediante `PATCH /api/user/complete-profile`.

### Código completo

```javascript
"use client";

import { useEffect, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { useAuthStore } from "@/shared/stores/useAuthStore";
import { UserService } from "@/services/user.service";
import { motion } from "framer-motion";

/**
 * @page AuthCallbackPage
 * @description Página de callback para autenticación social (Google, GitHub, Discord).
 *
 * Flujo:
 * 1. El usuario hace clic en "Continuar con Google"
 * 2. El navegador redirige a /api/auth/social/google (backend)
 * 3. El backend redirige a Google para autenticación
 * 4. Google redirige de vuelta al backend (?code=xxx)
 * 5. El backend intercambia el código, crea usuario si es necesario,
 *    genera un token Sanctum, y redirige al frontend:
 *    FRONTEND_URL/auth/callback?token=xxx
 * 6. ESTA PÁGINA captura el token y completa la autenticación
 *
 * ✅ Patrón aplicado: §16 (Componente con "use client")
 */
export default function AuthCallbackPage() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const setAuth = useAuthStore((state) => state.setAuth);
  const [status, setStatus] = useState("processing"); // processing | success | error

  useEffect(() => {
    /**
     * Procesa el token recibido en la URL.
     *
     * El token viene como query param: /auth/callback?token=1|abc...
     * Si no hay token, significa que algo salió mal en el flujo OAuth.
     */
    const processCallback = async () => {
      const token = searchParams.get("token");

      if (!token) {
        setStatus("error");
        // Si no hay token, redirigimos al login después de 2 segundos
        setTimeout(() => {
          router.push("/login?error=social_auth_failed");
        }, 2000);
        return;
      }

      try {
        // 1. Guardamos el token temporalmente para poder hacer peticiones
        //    (aún no tenemos los datos del usuario)
        // Nota: usamos setAuth temporal con un user vacío
        useAuthStore.getState().setAuth(null, token);

        // 2. Obtenemos los datos del usuario autenticado
        const userResponse = await UserService.getCurrentUser();

        // 3. Guardamos todo en el store (usuario + token)
        setAuth(userResponse.data, token);

        setStatus("success");

        // 4. Redirigimos al dashboard
        setTimeout(() => {
          router.push("/");
        }, 1000);
      } catch (error) {
        console.error("Error en callback social:", error);
        setStatus("error");
        setTimeout(() => {
          router.push("/login?error=social_auth_failed");
        }, 2000);
      }
    };

    processCallback();
  }, [searchParams, router, setAuth]);

  return (
    <div className="min-h-screen w-full bg-forest-dark flex items-center justify-center p-4">
      <div className="bg-forest-card/60 backdrop-blur-xl border border-forest-border p-8 sm:p-10 rounded-4xl shadow-2xl max-w-md w-full text-center">
        {status === "processing" && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="flex flex-col items-center gap-6"
          >
            {/* Spinner */}
            <motion.div
              animate={{ rotate: 360 }}
              transition={{ repeat: Infinity, duration: 1, ease: "linear" }}
              className="w-12 h-12 border-3 border-forest-accent border-t-transparent rounded-full"
            />
            <div>
              <h2 className="text-xl font-black text-forest-light mb-2">
                Autenticando...
              </h2>
              <p className="text-forest-muted text-sm">
                Estamos verificando tu identidad.
              </p>
            </div>
          </motion.div>
        )}

        {status === "success" && (
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            className="flex flex-col items-center gap-4"
          >
            <div className="w-16 h-16 rounded-full bg-forest-accent/10 flex items-center justify-center">
              <svg className="w-8 h-8 text-forest-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M5 13l4 4L19 7" />
              </svg>
            </div>
            <div>
              <h2 className="text-xl font-black text-forest-light mb-2">
                ¡Autenticación exitosa!
              </h2>
              <p className="text-forest-muted text-sm">
                Redirigiendo al dashboard...
              </p>
            </div>
          </motion.div>
        )}

        {status === "error" && (
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            className="flex flex-col items-center gap-4"
          >
            <div className="w-16 h-16 rounded-full bg-red-500/10 flex items-center justify-center">
              <svg className="w-8 h-8 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2.5} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </div>
            <div>
              <h2 className="text-xl font-black text-forest-light mb-2">
                Error de autenticación
              </h2>
              <p className="text-forest-muted text-sm">
                No se pudo completar el inicio de sesión. Redirigiendo...
              </p>
            </div>
          </motion.div>
        )}
      </div>

      {/* Footer */}
      <div className="absolute bottom-6 left-0 w-full text-center pointer-events-none">
        <p className="text-forest-muted/40 text-[11px] font-bold tracking-widest uppercase">
          Vyntra Protocol — Secure Access
        </p>
      </div>
    </div>
  );
}
```

### Explicación del flujo

```
1. Usuario clica "Continuar con Google"
2. window.location.href = "/api/auth/social/google"
3. Backend redirige a accounts.google.com
4. Usuario autentica en Google
5. Google redirige a /api/auth/social/google/callback?code=xxx
6. Backend procesa, genera token, redirige a:
   FRONTEND_URL/auth/callback?token=1|abc...
7. auth/callback/page.js:
   a. Lee token de URL
   b. Hace fetch a GET /api/user para obtener datos
   c. Guarda user + token en useAuthStore
   d. Redirige a /
```

### ⚠️ Importante: Seguridad

El token viaja en la URL como query param. Esto significa que:
- El token queda en el historial del navegador
- Podría ser enviado como Referer header a otros sitios

**Mitigación**: Una vez que el frontend captura el token, debería:
1. Limpiar la URL (eliminar el query param) usando `router.replace()` en lugar de `router.push()`
2. No hacer clic en links externos desde esta página

---

## 9. Paso 7: Crear página /auth/2fa

**Archivo:** `src/app/auth/2fa/page.js`

### ¿Qué hace esta página?

Cuando un usuario tiene 2FA activado y hace login, el backend responde con `requires_2fa: true`. El frontend guarda un token "parcial" y redirige a esta página donde el usuario debe ingresar el código de 6 dígitos de su app autenticadora.

### Código completo

```javascript
"use client";

import React, { useState, useRef, useEffect } from "react";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";
import { FiShield, FiArrowRight } from "react-icons/fi";
import { useVerify2FA } from "@/shared/hooks/useAuth";
import { useAuthStore } from "@/shared/stores/useAuthStore";

/**
 * @page TwoFactorPage
 * @description Pantalla de verificación de dos factores (2FA).
 *
 * Se muestra después del login cuando el usuario tiene 2FA activado.
 * El usuario debe ingresar el código de 6 dígitos de su app autenticadora
 * (Google Authenticator, Authy, etc.).
 *
 * ✅ Patrón aplicado: §16 ("use client" para hooks y estado)
 */
export default function TwoFactorPage() {
  const router = useRouter();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const requires_2fa = useAuthStore((state) => state.requires_2fa);

  const [code, setCode] = useState(["", "", "", "", "", ""]);
  const [showBackupOption, setShowBackupOption] = useState(false);
  const [backupCode, setBackupCode] = useState("");
  const inputRefs = useRef([]);

  const verifyMutation = useVerify2FA();

  // ─── Redirección si no hay 2FA pendiente ────────────────────
  // Si el usuario ya está autenticado o no tiene 2FA pendiente,
  // redirigimos al dashboard.
  useEffect(() => {
    if (isAuthenticated) {
      router.push("/");
    } else if (!requires_2fa) {
      router.push("/login");
    }
  }, [isAuthenticated, requires_2fa, router]);

  /**
   * Maneja el cambio en cada input de código.
   * Auto-avanza al siguiente input cuando se escribe un dígito.
   */
  const handleChange = (index, value) => {
    // Solo permitimos 1 dígito por input
    if (value.length > 1) return;
    // Solo permitimos dígitos
    if (value && !/^\d$/.test(value)) return;

    const newCode = [...code];
    newCode[index] = value;
    setCode(newCode);

    // Auto-avanzar al siguiente input
    if (value && index < 5) {
      inputRefs.current[index + 1]?.focus();
    }

    // Si completamos los 6 dígitos, enviamos automáticamente
    if (newCode.every((d) => d !== "") && index === 5) {
      handleVerify(newCode.join(""));
    }
  };

  /**
   * Maneja la tecla Backspace: retrocede al input anterior.
   */
  const handleKeyDown = (index, e) => {
    if (e.key === "Backspace" && !code[index] && index > 0) {
      inputRefs.current[index - 1]?.focus();
    }
  };

  /**
   * Envía el código de 6 dígitos al backend para verificación.
   */
  const handleVerify = (fullCode) => {
    if (fullCode.length !== 6) return;
    verifyMutation.mutate(fullCode);
  };

  /**
   * Envía el código de respaldo (si el usuario perdió el acceso a la app).
   */
  const handleBackupVerify = () => {
    if (backupCode.length < 8) return;
    verifyMutation.mutate(backupCode);
  };

  const codeString = code.join("");

  return (
    <div className="min-h-screen w-full bg-forest-dark flex items-center justify-center p-4 relative overflow-hidden">
      {/* Glows decorativos */}
      <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-forest-accent/5 blur-[120px] rounded-full pointer-events-none" />
      <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-forest-accent/10 blur-[120px] rounded-full pointer-events-none" />

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.6, ease: "easeOut" }}
        className="w-full max-w-[440px] z-10"
      >
        <div className="bg-forest-card/60 backdrop-blur-xl border border-forest-border p-8 sm:p-10 rounded-4xl shadow-2xl">
          <div className="text-center mb-8">
            {/* Icono */}
            <div className="w-16 h-16 rounded-full bg-forest-accent/10 flex items-center justify-center mx-auto mb-4">
              <FiShield size={28} className="text-forest-accent" />
            </div>

            <h1 className="text-2xl font-black text-forest-light tracking-tight mb-2">
              Verificación en dos pasos
            </h1>
            <p className="text-forest-muted text-sm font-medium">
              Ingresa el código de 6 dígitos de tu aplicación autenticadora.
            </p>
          </div>

          {/* Inputs de código */}
          <div className="flex justify-center gap-2 sm:gap-3 mb-8">
            {code.map((digit, index) => (
              <input
                key={index}
                ref={(el) => (inputRefs.current[index] = el)}
                type="text"
                inputMode="numeric"
                maxLength={1}
                value={digit}
                onChange={(e) => handleChange(index, e.target.value)}
                onKeyDown={(e) => handleKeyDown(index, e)}
                className="w-12 h-14 sm:w-14 sm:h-16 bg-forest-dark/60 border-2 border-forest-border/40 rounded-xl text-center text-2xl font-black text-forest-light outline-none transition-all focus:border-forest-accent/50 focus:bg-forest-dark/80 focus:shadow-[0_0_20px_rgba(34,197,94,0.1)]"
              />
            ))}
          </div>

          {/* Botón de verificar (solo si hay 6 dígitos) */}
          {codeString.length === 6 && !verifyMutation.isPending && (
            <motion.button
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              onClick={() => handleVerify(codeString)}
              className="w-full h-14 bg-forest-accent hover:bg-forest-light text-forest-dark font-black rounded-2xl transition-all shadow-[0_10px_30px_rgba(34,197,94,0.15)] flex items-center justify-center gap-2 group"
            >
              Verificar
              <FiArrowRight className="transition-transform group-hover:translate-x-1" />
            </motion.button>
          )}

          {/* Spinner durante verificación */}
          {verifyMutation.isPending && (
            <div className="flex justify-center">
              <motion.div
                animate={{ rotate: 360 }}
                transition={{ repeat: Infinity, duration: 1, ease: "linear" }}
                className="w-10 h-10 border-3 border-forest-accent border-t-transparent rounded-full"
              />
            </div>
          )}

          {/* Error */}
          {verifyMutation.isError && (
            <p className="text-red-400 text-sm text-center mt-4 font-medium">
              {verifyMutation.error.message}
            </p>
          )}

          {/* Link a código de respaldo */}
          <div className="text-center mt-6">
            <button
              type="button"
              onClick={() => setShowBackupOption(!showBackupOption)}
              className="text-forest-muted hover:text-forest-light text-sm font-medium transition-colors underline underline-offset-4 decoration-forest-border/30"
            >
              {showBackupOption
                ? "Usar código de verificación"
                : "¿Perdiste el acceso? Usa un código de respaldo"}
            </button>
          </div>

          {/* Input de código de respaldo */}
          {showBackupOption && (
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              className="mt-4 flex flex-col gap-3"
            >
              <p className="text-forest-muted text-xs font-medium text-center">
                Ingresa uno de tus códigos de respaldo de 8 caracteres.
              </p>
              <input
                type="text"
                value={backupCode}
                onChange={(e) => setBackupCode(e.target.value)}
                placeholder="XXXX-XXXX"
                className="w-full bg-forest-dark/40 border-2 border-forest-border/40 rounded-2xl px-4 py-3.5 text-forest-light text-center tracking-widest font-mono outline-none transition-all focus:border-forest-accent/50"
              />
              <button
                onClick={handleBackupVerify}
                disabled={backupCode.length < 8 || verifyMutation.isPending}
                className="w-full h-12 bg-forest-stat hover:bg-forest-stat/80 text-forest-light font-bold rounded-2xl transition-colors disabled:opacity-50"
              >
                Verificar código de respaldo
              </button>
            </motion.div>
          )}
        </div>
      </motion.div>

      {/* Footer */}
      <div className="absolute bottom-6 left-0 w-full text-center pointer-events-none">
        <p className="text-forest-muted/40 text-[11px] font-bold tracking-widest uppercase">
          Vyntra Protocol — Secure Access
        </p>
      </div>
    </div>
  );
}
```

### Características clave

| Feature | Implementación |
|:---|:---|
| **Auto-avance** | Al escribir un dígito, el foco pasa al siguiente input automáticamente |
| **Auto-envío** | Al completar el 6º dígito, se envía automáticamente |
| **Solo dígitos** | Validación: solo números, 1 por input |
| **Backspace** | Retrocede al input anterior |
| **Código de respaldo** | Opción expandible para usuarios que perdieron acceso a la app |
| **Protección de ruta** | Si no hay 2FA pendiente o ya está autenticado, redirige |

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §16 "use client" | ✅ Necesario para hooks y estado local |
| §12 Protocolo VYNE | ✅ `useVerify2FA().mutate()` con manejo de estados |
| §18 YAGNI | ✅ Solo mostramos lo que el usuario necesita en cada paso |

---

## 10. Paso 8: Crear página /settings/sessions

**Archivo:** `src/app/settings/sessions/page.js`

### ¿Qué hace esta página?

Muestra todas las sesiones (dispositivos) donde el usuario ha iniciado sesión. Es similar a la página de seguridad de Discord/Facebook.

### Código completo

```javascript
"use client";

import React from "react";
import { useSessions } from "@/shared/hooks/useAuth";
import { FiMonitor, FiSmartphone, FiX, FiArrowLeft } from "react-icons/fi";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";

/**
 * @page SessionsPage
 * @description Muestra las sesiones activas del usuario y permite cerrarlas.
 *
 * Usa el hook useSessions que combina:
 * - useQuery para obtener la lista de sesiones
 * - useMutation para revocar una sesión específica
 *
 * ✅ Patrón aplicado: §11 (useQuery con enabled: !!token)
 * ✅ Patrón aplicado: §16 (Componente con "use client")
 */
export default function SessionsPage() {
  const router = useRouter();
  const { sessions, isLoading, revokeSession, isRevoking } = useSessions();

  /**
   * Agrupa las sesiones: la actual primero, luego las demás.
   */
  const currentSession = sessions.find((s) => s.is_current);
  const otherSessions = sessions.filter((s) => !s.is_current);

  return (
    <div className="min-h-screen bg-forest-dark p-4 sm:p-8">
      <div className="max-w-3xl mx-auto">
        {/* ─── Header ─────────────────────────────────────── */}
        <div className="flex items-center gap-4 mb-8">
          <button
            onClick={() => router.back()}
            className="w-9 h-9 rounded-full bg-forest-card border border-forest-border flex items-center justify-center text-forest-muted hover:text-forest-light hover:border-forest-accent/30 transition-all"
          >
            <FiArrowLeft size={16} />
          </button>
          <div>
            <h1 className="text-2xl font-black text-forest-light tracking-tight">
              Sesiones activas
            </h1>
            <p className="text-forest-muted text-sm mt-1">
              Dispositivos donde has iniciado sesión.
            </p>
          </div>
        </div>

        {/* ─── Estado de carga ────────────────────────────── */}
        {isLoading && (
          <div className="space-y-3">
            {[1, 2, 3].map((i) => (
              <div
                key={i}
                className="h-20 bg-forest-card/50 rounded-xl animate-pulse"
              />
            ))}
          </div>
        )}

        {/* ─── Sesión actual ──────────────────────────────── */}
        {currentSession && !isLoading && (
          <div className="mb-6">
            <h2 className="text-xs font-bold uppercase tracking-widest text-forest-muted/60 mb-3 px-1">
              Sesión actual
            </h2>
            <SessionCard session={currentSession} isCurrent />
          </div>
        )}

        {/* ─── Otras sesiones ─────────────────────────────── */}
        {otherSessions.length > 0 && !isLoading && (
          <div>
            <h2 className="text-xs font-bold uppercase tracking-widest text-forest-muted/60 mb-3 px-1">
              Otros dispositivos
            </h2>
            <div className="space-y-2">
              {otherSessions.map((session) => (
                <SessionCard
                  key={session.uuid}
                  session={session}
                  onTerminate={() => revokeSession(session.uuid)}
                  isRevoking={isRevoking}
                />
              ))}
            </div>
          </div>
        )}

        {/* ─── Sin sesiones ───────────────────────────────── */}
        {!isLoading && sessions.length === 0 && (
          <div className="text-center py-16">
            <FiMonitor size={48} className="mx-auto text-forest-muted/30 mb-4" />
            <p className="text-forest-muted font-medium">
              No hay sesiones activas.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
//  COMPONENTE INTERNO: SessionCard
// ═══════════════════════════════════════════════════════════════════

/**
 * @component SessionCard
 * @description Tarjeta individual de sesión con info del dispositivo.
 *
 * @param {Object} session - Datos de la sesión
 * @param {boolean} isCurrent - Si es la sesión actual
 * @param {Function} onTerminate - Callback para cerrar la sesión
 * @param {boolean} isRevoking - Si hay una revocación en curso
 */
function SessionCard({ session, isCurrent = false, onTerminate, isRevoking }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      className="bg-forest-card border border-forest-border rounded-xl px-4 py-4 flex items-center gap-4"
    >
      {/* Icono según tipo de dispositivo */}
      <div
        className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${
          isCurrent
            ? "bg-forest-accent/10 text-forest-accent"
            : "bg-forest-stat text-forest-muted"
        }`}
      >
        {session.type === "mobile" ? (
          <FiSmartphone size={18} />
        ) : (
          <FiMonitor size={18} />
        )}
      </div>

      {/* Información del dispositivo */}
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-forest-light font-semibold text-sm">
            {session.os} · {session.browser}
          </span>
          {isCurrent && (
            <span className="px-1.5 py-0.5 bg-forest-accent/15 border border-forest-accent/25 text-forest-accent text-[10px] font-bold rounded uppercase tracking-widest">
              Actual
            </span>
          )}
        </div>
        <p className="text-forest-muted-alt text-xs mt-0.5 truncate">
          {session.location} · IP: {session.ip}
        </p>
      </div>

      {/* Botón de cerrar sesión */}
      {!isCurrent && (
        <button
          onClick={onTerminate}
          disabled={isRevoking}
          className="w-8 h-8 rounded-md bg-forest-stat hover:bg-forest-danger/20 text-forest-muted hover:text-forest-danger flex items-center justify-center transition-colors shrink-0 disabled:opacity-50"
          title="Cerrar esta sesión"
        >
          <FiX size={14} strokeWidth={2.5} />
        </button>
      )}
    </motion.div>
  );
}
```

### Estructura de datos esperada del backend

```json
{
  "status": "success",
  "data": [
    {
      "uuid": "string",
      "os": "Windows",
      "browser": "Chrome 125",
      "ip": "192.168.1.45",
      "location": "Madrid, España",
      "is_current": true,
      "type": "desktop"
    }
  ]
}
```

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §11 useQuery | ✅ Sesiones con `useQuery({ queryKey: ['user_sessions'], enabled: !!token })` |
| §12 useMutation | ✅ Revocación con `useMutation({ mutationFn, onSuccess, onError })` |
| §16 "use client" | ✅ Hooks de estado y efectos |

---

## 11. Paso 9: Crear página /settings/security

**Archivo:** `src/app/settings/security/page.js`

### ¿Qué hace esta página?

Centraliza todas las opciones de seguridad de la cuenta:
1. **Estado de verificación de email** (verificado/pendiente + botón de reenvío)
2. **Cambio de contraseña** (con barra de fortaleza)
3. **2FA** (activar/desactivar)
4. **Sesiones activas** (lista de dispositivos con opción de cierre)

### Código completo

```javascript
"use client";

import React, { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { motion } from "framer-motion";
import { FiShield, FiArrowLeft, FiCheck, FiX, FiLoader } from "react-icons/fi";
import { useCurrentUser } from "@/features/users/hooks/useCurrentUser";
import { useSessions, useResendVerification } from "@/shared/hooks/useAuth";
import { useAuthStore } from "@/shared/stores/useAuthStore";
import { toast } from "sonner";

/**
 * @page SecurityPage
 * @description Página de seguridad de la cuenta: verificación email,
 * cambio de contraseña, 2FA y sesiones activas.
 */
export default function SecurityPage() {
  const router = useRouter();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);

  // Redirigir si no está autenticado
  useEffect(() => {
    if (!isAuthenticated) {
      router.push("/login");
    }
  }, [isAuthenticated, router]);

  return (
    <div className="min-h-screen bg-forest-dark p-4 sm:p-8">
      <div className="max-w-3xl mx-auto">
        {/* Header */}
        <div className="flex items-center gap-4 mb-8">
          <button
            onClick={() => router.back()}
            className="w-9 h-9 rounded-full bg-forest-card border border-forest-border flex items-center justify-center text-forest-muted hover:text-forest-light hover:border-forest-accent/30 transition-all"
          >
            <FiArrowLeft size={16} />
          </button>
          <div>
            <h1 className="text-2xl font-black text-forest-light tracking-tight">
              Seguridad
            </h1>
            <p className="text-forest-muted text-sm mt-1">
              Administra la seguridad de tu cuenta.
            </p>
          </div>
        </div>

        <div className="flex flex-col gap-8">
          {/* ═══════════════════════════════════════════════
               SECCIÓN 1: Verificación de Email
               ═══════════════════════════════════════════════ */}
          <EmailVerificationSection />

          <div className="bg-forest-border/30 h-px w-full" />

          {/* ═══════════════════════════════════════════════
               SECCIÓN 2: Cambio de Contraseña
               ═══════════════════════════════════════════════ */}
          <ChangePasswordSection />

          <div className="bg-forest-border/30 h-px w-full" />

          {/* ═══════════════════════════════════════════════
               SECCIÓN 3: Autenticación de Dos Factores (2FA)
               ═══════════════════════════════════════════════ */}
          <TwoFactorSection />

          <div className="bg-forest-border/30 h-px w-full" />

          {/* ═══════════════════════════════════════════════
               SECCIÓN 4: Sesiones Activas
               ═══════════════════════════════════════════════ */}
          <SessionsSection />
        </div>
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
//  SECCIÓN 1: Verificación de Email
// ═══════════════════════════════════════════════════════════════════

function EmailVerificationSection() {
  const { data: user } = useCurrentUser();
  const resendMutation = useResendVerification();
  const [cooldown, setCooldown] = useState(0);

  const handleResend = () => {
    resendMutation.mutate();
    // Cooldown de 60 segundos para evitar spam
    setCooldown(60);
    const interval = setInterval(() => {
      setCooldown((prev) => {
        if (prev <= 1) {
          clearInterval(interval);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);
  };

  const isVerified = user?.email_verified_at;

  return (
    <div className="bg-forest-card border border-forest-border rounded-xl p-5">
      <div className="flex items-center justify-between gap-4 flex-wrap">
        <div className="flex items-center gap-3">
          <div
            className={`w-10 h-10 rounded-full flex items-center justify-center shrink-0 ${
              isVerified
                ? "bg-forest-accent/10 text-forest-accent"
                : "bg-yellow-500/10 text-yellow-400"
            }`}
          >
            {isVerified ? <FiCheck size={18} /> : <FiX size={18} />}
          </div>
          <div>
            <h3 className="text-forest-light font-bold text-sm">
              Verificación de correo electrónico
            </h3>
            <p className="text-forest-muted-alt text-xs mt-0.5">
              {isVerified
                ? "Tu correo electrónico está verificado."
                : "Verifica tu correo para acceder a todas las funciones."}
            </p>
          </div>
        </div>

        {!isVerified && (
          <button
            onClick={handleResend}
            disabled={cooldown > 0 || resendMutation.isPending}
            className="px-4 py-2 bg-forest-stat border border-forest-border rounded-lg text-forest-light font-semibold text-sm hover:bg-forest-stat/80 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {resendMutation.isPending
              ? "Enviando..."
              : cooldown > 0
              ? `Reenviar en ${cooldown}s`
              : "Reenviar correo"}
          </button>
        )}
      </div>
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
//  SECCIÓN 2: Cambio de Contraseña
// ═══════════════════════════════════════════════════════════════════

function ChangePasswordSection() {
  const [currentPassword, setCurrentPassword] = useState("");
  const [newPassword, setNewPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [showPasswords, setShowPasswords] = useState(false);

  // Barra de fortaleza
  const strength = calcPasswordStrength(newPassword);
  const STRENGTH_LABELS = ["", "Débil", "Regular", "Fuerte"];

  const canSubmit =
    currentPassword.length >= 4 &&
    newPassword.length >= 8 &&
    confirmPassword === newPassword;

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!canSubmit) return;
    // Aquí se llamaría a useMutateUser() para cambiar la contraseña
    toast.success("Contraseña actualizada");
    setCurrentPassword("");
    setNewPassword("");
    setConfirmPassword("");
  };

  return (
    <SectionCard title="Cambiar contraseña">
      <form onSubmit={handleSubmit} className="flex flex-col gap-4">
        <div>
          <label className="text-forest-muted text-xs font-bold uppercase tracking-wider mb-1.5 block">
            Contraseña actual
          </label>
          <input
            type={showPasswords ? "text" : "password"}
            value={currentPassword}
            onChange={(e) => setCurrentPassword(e.target.value)}
            placeholder="••••••••"
            className="w-full bg-forest-dark/40 border border-forest-border/40 rounded-lg px-4 py-2.5 text-forest-light placeholder-forest-muted/30 outline-none focus:border-forest-accent/50 transition-colors"
          />
        </div>

        <div>
          <label className="text-forest-muted text-xs font-bold uppercase tracking-wider mb-1.5 block">
            Nueva contraseña
          </label>
          <input
            type={showPasswords ? "text" : "password"}
            value={newPassword}
            onChange={(e) => setNewPassword(e.target.value)}
            placeholder="Mínimo 8 caracteres"
            className="w-full bg-forest-dark/40 border border-forest-border/40 rounded-lg px-4 py-2.5 text-forest-light placeholder-forest-muted/30 outline-none focus:border-forest-accent/50 transition-colors"
          />
          {/* Barra de fortaleza */}
          {newPassword.length > 0 && (
            <div className="flex items-center gap-2 mt-2">
              <div className="flex gap-1 flex-1 h-1.5">
                <div
                  className={`h-full flex-1 rounded-full transition-colors ${
                    strength >= 1 ? "bg-red-500" : "bg-forest-border"
                  }`}
                />
                <div
                  className={`h-full flex-1 rounded-full transition-colors ${
                    strength >= 2 ? "bg-yellow-500" : "bg-forest-border"
                  }`}
                />
                <div
                  className={`h-full flex-1 rounded-full transition-colors ${
                    strength >= 3 ? "bg-forest-accent" : "bg-forest-border"
                  }`}
                />
              </div>
              <span className="text-xs font-semibold text-forest-muted-alt w-12 text-right">
                {STRENGTH_LABELS[strength]}
              </span>
            </div>
          )}
        </div>

        <div>
          <label className="text-forest-muted text-xs font-bold uppercase tracking-wider mb-1.5 block">
            Confirmar nueva contraseña
          </label>
          <input
            type={showPasswords ? "text" : "password"}
            value={confirmPassword}
            onChange={(e) => setConfirmPassword(e.target.value)}
            placeholder="Repite la contraseña"
            className="w-full bg-forest-dark/40 border border-forest-border/40 rounded-lg px-4 py-2.5 text-forest-light placeholder-forest-muted/30 outline-none focus:border-forest-accent/50 transition-colors"
          />
        </div>

        <button
          type="submit"
          disabled={!canSubmit}
          className="self-start px-6 py-2.5 bg-forest-accent hover:bg-forest-light text-forest-dark font-bold rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Guardar contraseña
        </button>
      </form>
    </SectionCard>
  );
}

// ═══════════════════════════════════════════════════════════════════
//  SECCIÓN 3: 2FA (Two-Factor Authentication)
// ═══════════════════════════════════════════════════════════════════

function TwoFactorSection() {
  const [showQR, setShowQR] = useState(false);
  const [qrCode, setQrCode] = useState("");
  const [verifyCode, setVerifyCode] = useState("");
  const [backupCodes, setBackupCodes] = useState([]);

  // Estado simulado: 2FA desactivado por defecto
  const [is2FAEnabled, setIs2FAEnabled] = useState(false);

  const handleEnable2FA = async () => {
    try {
      // En producción: llamar a AuthService.enable2FA(token)
      setQrCode("otpauth://totp/Vyntra:user@example.com?secret=MOCKQR&issuer=Vyntra");
      setShowQR(true);
    } catch (error) {
      toast.error("Error al activar 2FA");
    }
  };

  const handleConfirm2FA = async () => {
    if (verifyCode.length !== 6) return;
    try {
      // En producción: llamar a AuthService.confirm2FA(token, verifyCode)
      setBackupCodes(["ABCD-1234", "EFGH-5678", "IJKL-9012", "MNOP-3456"]);
      setIs2FAEnabled(true);
      setShowQR(false);
      setVerifyCode("");
      toast.success("2FA activado correctamente");
    } catch (error) {
      toast.error("Código inválido");
    }
  };

  const handleDisable2FA = async () => {
    try {
      // En producción: llamar a AuthService.disable2FA(token, password, code)
      setIs2FAEnabled(false);
      setBackupCodes([]);
      toast.success("2FA desactivado");
    } catch (error) {
      toast.error("Error al desactivar 2FA");
    }
  };

  return (
    <SectionCard
      title="Autenticación de dos factores (2FA)"
      description="Añade una capa extra de seguridad a tu cuenta."
    >
      {!is2FAEnabled ? (
        // ─── 2FA DESACTIVADO ─────────────────────────────────
        <div>
          {!showQR ? (
            <button
              onClick={handleEnable2FA}
              className="px-6 py-2.5 bg-forest-accent hover:bg-forest-light text-forest-dark font-bold rounded-lg transition-colors"
            >
              Activar 2FA
            </button>
          ) : (
            // ─── Paso 1: Mostrar QR ──────────────────────────
            <motion.div
              initial={{ opacity: 0, height: 0 }}
              animate={{ opacity: 1, height: "auto" }}
              className="flex flex-col gap-4"
            >
              <div className="bg-white p-4 rounded-xl inline-block self-center">
                {/* En producción aquí va el QR real */}
                <div className="w-48 h-48 bg-gray-200 rounded flex items-center justify-center text-gray-500 text-sm font-mono">
                  QR CODE
                </div>
              </div>
              <p className="text-forest-muted text-xs text-center">
                Escanea este código con Google Authenticator o Authy.
              </p>

              {/* ─── Paso 2: Verificar código ────────────────── */}
              <div className="flex flex-col items-center gap-3">
                <p className="text-forest-muted text-xs font-medium">
                  Ingresa el código de 6 dígitos de la app:
                </p>
                <input
                  type="text"
                  inputMode="numeric"
                  maxLength={6}
                  value={verifyCode}
                  onChange={(e) => setVerifyCode(e.target.value)}
                  placeholder="123456"
                  className="w-40 bg-forest-dark/40 border border-forest-border/40 rounded-lg px-4 py-2.5 text-center text-lg tracking-widest font-mono text-forest-light outline-none focus:border-forest-accent/50"
                />
                <button
                  onClick={handleConfirm2FA}
                  disabled={verifyCode.length !== 6}
                  className="px-6 py-2.5 bg-forest-accent hover:bg-forest-light text-forest-dark font-bold rounded-lg transition-colors disabled:opacity-50"
                >
                  Confirmar
                </button>
              </div>
            </motion.div>
          )}
        </div>
      ) : (
        // ─── 2FA ACTIVADO ────────────────────────────────────
        <div className="flex flex-col gap-4">
          {/* Backup codes */}
          {backupCodes.length > 0 && (
            <div className="bg-forest-dark/40 border border-yellow-500/20 rounded-lg p-4">
              <p className="text-yellow-400 text-xs font-bold uppercase tracking-wider mb-2">
                ⚠️ Códigos de respaldo
              </p>
              <p className="text-forest-muted text-xs mb-3">
                Guarda estos códigos en un lugar seguro. Si pierdes el acceso
                a tu app autenticadora, podrás usar uno de estos códigos.
              </p>
              <div className="grid grid-cols-2 gap-2">
                {backupCodes.map((code, i) => (
                  <code
                    key={i}
                    className="bg-forest-dark/60 px-3 py-1.5 rounded text-forest-light font-mono text-xs text-center"
                  >
                    {code}
                  </code>
                ))}
              </div>
            </div>
          )}

          <div className="flex items-center gap-2">
            <FiShield size={16} className="text-forest-accent" />
            <span className="text-forest-accent text-sm font-semibold">
              2FA activado
            </span>
          </div>

          <button
            onClick={handleDisable2FA}
            className="self-start px-4 py-2 bg-forest-danger/10 border border-forest-danger/30 text-forest-danger font-bold rounded-lg hover:bg-forest-danger/20 transition-colors text-sm"
          >
            Desactivar 2FA
          </button>
        </div>
      )}
    </SectionCard>
  );
}

// ═══════════════════════════════════════════════════════════════════
//  SECCIÓN 4: Sesiones Activas
// ═══════════════════════════════════════════════════════════════════

function SessionsSection() {
  const { sessions, isLoading, revokeSession, isRevoking } = useSessions();

  const currentSession = sessions.find((s) => s.is_current);
  const otherSessions = sessions.filter((s) => !s.is_current);

  return (
    <SectionCard
      title="Sesiones activas"
      description="Dispositivos donde has iniciado sesión."
    >
      {isLoading ? (
        <div className="animate-pulse space-y-2">
          <div className="h-16 bg-forest-card/50 rounded-lg" />
          <div className="h-16 bg-forest-card/50 rounded-lg" />
        </div>
      ) : sessions.length === 0 ? (
        <p className="text-forest-muted text-sm">No hay sesiones activas.</p>
      ) : (
        <div className="space-y-2">
          {currentSession && (
            <SessionRow session={currentSession} isCurrent />
          )}
          {otherSessions.map((session) => (
            <SessionRow
              key={session.uuid}
              session={session}
              onTerminate={() => revokeSession(session.uuid)}
              isRevoking={isRevoking}
            />
          ))}
        </div>
      )}
    </SectionCard>
  );
}

// ═══════════════════════════════════════════════════════════════════
//  COMPONENTES INTERNOS REUTILIZABLES
// ═══════════════════════════════════════════════════════════════════

/**
 * @component SectionCard
 * @description Contenedor genérico para secciones de la página.
 */
function SectionCard({ title, description, children }) {
  return (
    <div>
      <div className="mb-4">
        <h2 className="text-forest-light font-bold text-base">{title}</h2>
        {description && (
          <p className="text-forest-muted text-xs mt-1">{description}</p>
        )}
      </div>
      <div className="bg-forest-card border border-forest-border rounded-xl p-5">
        {children}
      </div>
    </div>
  );
}

/**
 * @component SessionRow
 * @description Fila individual de sesión para la lista de sesiones activas.
 */
function SessionRow({ session, isCurrent, onTerminate, isRevoking }) {
  return (
    <div className="flex items-center gap-3 py-2.5 px-1">
      <div
        className={`w-8 h-8 rounded-full flex items-center justify-center shrink-0 ${
          isCurrent
            ? "bg-forest-accent/10 text-forest-accent"
            : "bg-forest-stat text-forest-muted"
        }`}
      >
        {session.type === "mobile" ? (
          <FiX size={14} />
        ) : (
          <FiShield size={14} />
        )}
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2">
          <span className="text-forest-light font-semibold text-sm">
            {session.os} · {session.browser}
          </span>
          {isCurrent && (
            <span className="px-1.5 py-0.5 bg-forest-accent/15 border border-forest-accent/25 text-forest-accent text-[10px] font-bold rounded uppercase tracking-widest">
              Actual
            </span>
          )}
        </div>
        <p className="text-forest-muted-alt text-xs mt-0.5">
          {session.location} · IP: {session.ip}
        </p>
      </div>
      {!isCurrent && (
        <button
          onClick={onTerminate}
          disabled={isRevoking}
          className="text-forest-muted hover:text-forest-danger transition-colors disabled:opacity-50"
          title="Cerrar sesión"
        >
          <X size={14} />
        </button>
      )}
    </div>
  );
}

// ═══════════════════════════════════════════════════════════════════
//  UTILIDADES
// ═══════════════════════════════════════════════════════════════════

/**
 * Calcula la fortaleza de una contraseña.
 * @param {string} val - Contraseña a evaluar.
 * @returns {number} 0 (vacía), 1 (débil), 2 (regular), 3 (fuerte)
 */
function calcPasswordStrength(val) {
  if (!val) return 0;
  let s = 1;
  if (/[A-Z]/.test(val) && /[0-9]/.test(val)) s = 2;
  if (s === 2 && /[^A-Za-z0-9]/.test(val) && val.length >= 8) s = 3;
  return s;
}
```

> **Nota:** Esta página importa `useCurrentUser` desde `@/features/users/hooks/useCurrentUser`. Si ese hook no existe aún, asegúrate de crearlo siguiendo el patrón de los hooks existentes en `src/features/users/hooks/`.

### Secciones de la página

| Sección | Propósito | Hook usado |
|:---|:---|:---|
| **Verificación email** | Muestra estado y permite reenviar | `useCurrentUser` + `useResendVerification` |
| **Cambio contraseña** | Formulario con barra de fortaleza | (futuro) `useMutateUser` |
| **2FA** | Activar QR + confirmar + backup codes | (futuro) `AuthService.enable2FA` |
| **Sesiones activas** | Lista dispositivos con cierre remoto | `useSessions` |

### ✅ Patrones aplicados

| Patrón | Verificación |
|:---|:---:|
| §11 useQuery | ✅ `useCurrentUser()`, `useSessions()` |
| §12 useMutation | ✅ `useResendVerification().mutate()` |
| §16 "use client" | ✅ Hooks y estado local |
| Componentes internos | ✅ `SectionCard`, `SessionRow` separados para legibilidad |

---

## 12. Verificación Final

### Checklist de implementación

#### Archivos creados
- [ ] `src/services/auth.service.js` — 11 métodos de API
- [ ] `src/shared/stores/useAuthStore.js` — Zustand + persist
- [ ] `src/shared/hooks/useAuth.js` — 6 hooks (login, register, logout, verify2FA, sessions, resend)
- [ ] `src/app/auth/callback/page.js` — Socialite callback handler
- [ ] `src/app/auth/2fa/page.js` — Pantalla de verificación 2FA
- [ ] `src/app/settings/sessions/page.js` — Gestión de dispositivos
- [ ] `src/app/settings/security/page.js` — Seguridad de cuenta

#### Archivos modificados
- [ ] `src/features/auth/components/organisms/LoginForm.jsx` — Conectado a API real
- [ ] `src/features/auth/components/organisms/RegisterForm.jsx` — Campos expandidos + API real

#### Flujo de registro
- [ ] RegisterForm envía `{ username, first_name, last_name, email, password, password_confirmation }`
- [ ] Responde 201 con `{ user, token }`
- [ ] Token guardado en Zustand store (persist en localStorage)
- [ ] Redirige a dashboard

#### Flujo de login
- [ ] LoginForm envía `{ email, password }`
- [ ] Responde 200 con `{ user, token }`
- [ ] Token guardado en Zustand store
- [ ] Redirige a dashboard
- [ ] Si `requires_2fa: true` → redirige a `/auth/2fa`

#### Flujo de logout
- [ ] Llama a `POST /api/auth/logout`
- [ ] Elimina token del store
- [ ] Invalida queries de React Query
- [ ] Redirige a login

#### Flujo social (Google)
- [ ] Botón "Continuar con Google" → `window.location.href` a Socialite
- [ ] Callback en `/auth/callback` recibe token de URL
- [ ] Fetch a `GET /api/user` para obtener datos
- [ ] Guarda sesión y redirige a dashboard

#### Flujo de sesiones
- [ ] `GET /api/user/sessions` lista dispositivos
- [ ] `DELETE /api/user/sessions/{uuid}` revoca sesión específica
- [ ] Refresca la lista después de revocar

#### Convenciones

| Regla | Verificación |
|:---|:---:|
| §11 — NUNCA useState para server data | ✅ Todo pasa por React Query o Zustand |
| §12 — useMutation con 3 ciclos | ✅ mutationFn, onSuccess/onError, onSettled |
| §13 — client_uuid | ✅ No aplica para auth (no crea recursos con idempotencia) |
| §15 — Servicios API | ✅ AuthService, mockRequest, JSDoc, snake_case |
| §16 — "use client" solo cuando necesario | ✅ Solo en componentes con hooks/eventos |
| §17 — Naming | ✅ PascalCase componentes, camelCase hooks/funciones |
| §18 — YAGNI | ✅ Solo enviamos lo que el consumidor necesita |

### Errores comunes y soluciones

| Problema | Causa | Solución |
|:---|:---|:---|
| `401 Unauthorized` en logout | Token no se envía en header | Verificar que `useAuthStore` tenga el token |
| Error CORS en peticiones | El backend no tiene configurado CORS | Agregar `'Access-Control-Allow-Origin'` o usar `laravel-cors` |
| El store no persiste al recargar | `partialize` excluye campos necesarios | Verificar que `token` y `user` estén en `partialize` |
| El spinner nunca se detiene | `onError` no resetea `isPending` | React Query maneja `isPending` automáticamente — no necesita reseteo manual |
| Google redirect no funciona | `getSocialRedirectUrl` mal construido | Verificar `NEXT_PUBLIC_API_URL` en `.env.local` |

---

> **Fin de la guía.**  
> Creada para la rama `feature/auth` del proyecto Vyntra.  
> Sigue el orden de los pasos y verifica cada checklist antes de avanzar.
