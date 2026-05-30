# 🔑 Guía Completa: Entendiendo Laravel Sanctum desde las entrañas

Esta guía explica detalladamente cómo funciona el motor de **Laravel Sanctum** por detrás, cómo se relaciona con el protocolo Bearer Token y qué significa cada línea del `AuthController` de Vyntra.

---

## 1. El Problema del "Estado" en la Web (State vs Stateless)

Por defecto, el protocolo HTTP es **Stateless** (sin estado). Esto significa que tu servidor de Laravel es "amnésico": cada petición que recibe es como si fuera de un completo extraño. No sabe si es la misma persona de hace un segundo.

Existen dos formas clásicas de solucionar esta amnesia:

### Método A: Sesiones y Cookies (Clásico)
* El servidor guarda un archivo de sesión y le da al navegador una "galleta" (cookie) con un ID. En cada petición, el navegador devuelve la galleta y el servidor la reconoce.
* **Problema:** Es difícil de escalar para aplicaciones móviles o frontends modernos que corren en dominios separados (como Next.js en Vercel).

### Método B: Bearer Tokens (El estándar moderno de las APIs)
* El usuario se identifica una vez (Login).
* El servidor genera un **Token** (una cadena de caracteres aleatoria y secreta, como una llave física).
* El servidor se la entrega al frontend.
* El frontend guarda esa llave y **en cada petición futura** se la envía al servidor en la cabecera (header) de la petición: `Authorization: Bearer <tu_token_secreto>`.
* El servidor recibe la llave, la valida, y si coincide, sabe exactamente quién es el usuario.

**Laravel Sanctum implementa el Método B (Tokens Personales de Acceso).**

---

## 2. El Motor por detrás: ¿Qué pasa en la Base de Datos?

Cuando instalas Sanctum, se crea una tabla física en PostgreSQL llamada `personal_access_tokens`:

```
                    TABLA: personal_access_tokens
┌──────┬───────────────┬───────────────────────────────────────┬────────────┐
│  ID  │ tokenable_id  │                 token                 │ last_used  │
├──────┼───────────────┼───────────────────────────────────────┼────────────┤
│  1   │  UUID-USER-A  │ sha256(llave_secreta_aleatoria...)    │ 2026-05-30 │
└──────┴───────────────┴───────────────────────────────────────┴────────────┘
```

1. **`tokenable_id`**: El UUID del usuario al que le pertenece esta llave.
2. **`token`**: La versión encriptada (hash SHA-256) de la llave para que, si alguien hackea tu base de datos, no pueda robar los tokens de los usuarios.

---

## 3. Explicación paso a paso de los Métodos del `AuthController`

A continuación, destripamos cada línea del controlador y explicamos la sintaxis.

### 📥 A. El Registro (Crear Usuario + Generar Token)

```php
public function register(StoreUserRequest $request): JsonResponse
{
    // 1. $request->validated() nos da un array con los datos que ya pasaron
    // los filtros del StoreUserRequest (ej. email único, passwords coinciden).
    $validated = $request->validated();

    // 2. Insertamos el usuario en PostgreSQL encriptando su password.
    $user = User::create([
        'username'   => $validated['username'],
        'user_tag'   => $validated['user_tag'],
        'first_name' => $validated['first_name'],
        'last_name'  => $validated['last_name'],
        'email'      => $validated['email'],
        'password'   => Hash::make($validated['password']), // Hash encripta
    ]);

    // 3. ¡Mágia de Sanctum! Le decimos al modelo $user que cree un nuevo token.
    // ->plainTextToken nos devuelve la llave en texto plano (ej: "1|abc123xyz...")
    // Este valor en texto plano es el que se le da al frontend. ¡Laravel no lo vuelve a mostrar jamás!
    $token = $user->createToken('auth_token')->plainTextToken;

    // 4. Respondemos con HTTP 201 (Created)
    return response()->json([
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'user'         => new UserResource($user),
    ], 201);
}
```

---

### 🔑 B. El Login (Delegar y Firmar Llave)

```php
public function login(LoginRequest $request): JsonResponse
{
    // 1. Llama al método del Request que creamos.
    // Si la contraseña o el email no coinciden, lanza ValidationException
    // y el código se detiene aquí al instante.
    $request->authenticate();

    // 2. Si pasó el authenticate(), significa que el usuario ya está autenticado en la sesión de Laravel.
    // Auth::user() obtiene el modelo del usuario desde la memoria del servidor.
    $user = Auth::user();
    
    // 3. Creamos un nuevo token para esta nueva sesión (por ejemplo, desde un nuevo navegador).
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'user'         => new UserResource($user),
    ], 200);
}
```

---

### 🚪 C. El Logout (Destruir la Llave)

```php
public function logout(Request $request): JsonResponse
{
    // 1. $request->user() obtiene al usuario autenticado que envió la petición.
    // 2. ->currentAccessToken() apunta a la fila específica de la tabla "personal_access_tokens"
    // con la cual se autenticó esta petición.
    // 3. ->delete() elimina esa fila de la base de datos para siempre.
    $request->user()->currentAccessToken()->delete();

    // A partir de este milisegundo, si el frontend intenta usar ese token,
    // Laravel responderá con "HTTP 401 Unauthorized" porque la llave ya no existe en la BD.
    return response()->json([
        'status'  => 'success',
        'message' => 'Sesión cerrada con éxito'
    ], 200);
}
```

---

## 4. ¿Cómo sabe una ruta si el Token es válido? (El Middleware)

En tus rutas (`routes/api.php`), protegerás los endpoints usando el middleware de Sanctum:

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/channels/{channel}/messages', [ChannelMessageController::class, 'store']);
});
```

### ¿Qué hace `auth:sanctum` cuando llega una petición a esa ruta?
1. **Intercepta** la petición y busca la cabecera `Authorization: Bearer <token_enviado>`.
2. Si no hay cabecera, **bloquea la petición** y devuelve `401 Unauthorized`.
3. Si hay cabecera, extrae el token, lo encripta en SHA-256 y busca una coincidencia en la tabla `personal_access_tokens`.
4. Si lo encuentra, asocia al usuario correspondiente a la variable `$request` para que puedas usar `$request->user()`.
5. Si es válido, deja pasar la petición al controlador. Si no, la bloquea.
