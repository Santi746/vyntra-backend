<?php
/* @noinspection ALL */
// @formatter:off
// phpcs:ignoreFile

/**
 * Complemento en español para el IDE Helper de Laravel.
 *
 * Proporciona documentación en español de las fachadas y métodos
 * más utilizados para no tener que leer la documentación original en inglés.
 * Este archivo NO se sobrescribe al regenerar _ide_helper.php.
 *
 * @see https://github.com/barryvdh/laravel-ide-helper
 */

namespace Illuminate\Support\Facades {

    /**
     * @mixin \Illuminate\Auth\AuthManager
     *
     * @method static \Illuminate\Contracts\Auth\Guard|\App\Models\User|null user() Obtiene el usuario autenticado de la solicitud actual. Retorna null si no hay sesión activa.
     * @method static bool check() Verifica si el usuario actual está autenticado (true = logueado).
     * @method static bool guest() Verifica si el usuario actual es un invitado (false = no logueado).
     * @method static string|int|null id() Obtiene el UUID del usuario autenticado actualmente.
     * @method static bool attempt(array $credentials, bool $remember = false) Intenta autenticar con credenciales [email => '', password => '']. Retorna true si éxito.
     * @method static bool validate(array $credentials = []) Valida credenciales sin iniciar sesión.
     * @method static bool once(array $credentials = []) Autentica al usuario solo para la solicitud actual (sin sesión ni cookie).
     * @method static \App\Models\User|null onceUsingId(mixed $id) Inicia sesión temporal con un UUID de usuario para la solicitud actual.
     * @method static \App\Models\User loginUsingId(mixed $id, bool $remember = false) Inicia sesión permanente con el UUID del usuario.
     * @method static bool viaRemember() Verifica si el usuario fue autenticado mediante la cookie "remember me".
     * @method static void logout() Cierra la sesión del usuario actual, invalida el token/ sesión.
     * @method static \Illuminate\Contracts\Auth\Guard guard(string|null $name = null) Cambia o accede al guard de autenticación (por defecto 'web' o 'sanctum').
     * @method static \Illuminate\Contracts\Auth\PasswordBroker passwordBroker(string|null $name = null) Obtiene el broker de restablecimiento de contraseñas.
     * @method static \Illuminate\Auth\SessionGuard|\Laravel\Sanctum\Guard setProvider(\Illuminate\Contracts\Auth\UserProvider $provider) Establece el proveedor de usuarios.
     * @method static \Illuminate\Contracts\Auth\Authenticatable|null getProvider() Obtiene el proveedor de usuarios actual.
     * @method static \Illuminate\Contracts\Auth\Authenticatable|null resolveUser() Resuelve el usuario sin persistir el estado en la sesión.
     */
    class Auth {}

    /**
     * @mixin \Illuminate\Validation\Factory
     *
     * @method static \Illuminate\Validation\Validator make(array $data, array $rules, array $messages = [], array $customAttributes = []) Crea una instancia de Validador con las reglas especificadas.
     * @method static array validate(array $data, array $rules, array $messages = [], array $customAttributes = []) Valida datos contra reglas y lanza ValidationException si falla (útil en controladores).
     * @method static void extend(string $rule, \Closure|string $extension, string|null $message = null) Registra una regla de validación personalizada.
     * @method static void replacer(string $rule, \Closure|string $replacer) Registra un reemplazador de mensajes de error para una regla personalizada.
     * @method static void resolver(\Closure $resolver) Establece un resolvedor de validadores personalizado.
     */
    class Validator {}

    /**
     * @mixin \Illuminate\Hashing\HashManager
     *
     * @method static string make(string $value, array $options = []) Hashea un texto plano (contraseña). Usa Bcrypt por defecto.
     * @method static bool check(string $value, string $hashedValue, array $options = []) Verifica si un texto plano coincide con un hash almacenado.
     * @method static bool needsRehash(string $hashedValue, array $options = []) Verifica si el hash debe ser rehasheado (cambiaron los cost factors).
     * @method static string info(string $hashedValue) Obtiene información del algoritmo y opciones usados en el hash.
     * @method static bool isHashed(string $value) Verifica si el valor ya parece un hash válido.
     * @method static array getDrivers() Obtiene los controladores de hashing disponibles (bcrypt, argon, argon2id).
     */
    class Hash {}

    /**
     * @mixin \Illuminate\Cache\CacheManager
     *
     * @method static mixed remember(string $key, \DateTimeInterface|\DateInterval|int $ttl, \Closure $callback) Recupera un valor del caché o lo almacena usando el callback si no existe.
     * @method static mixed rememberForever(string $key, \Closure $callback) Recupera o almacena un valor en caché sin expiración.
     * @method static mixed get(string $key, mixed $default = null) Recupera un valor del caché por su clave.
     * @method static bool set(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null) Almacena un valor en caché con tiempo de vida opcional.
     * @method static bool add(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null) Almacena solo si la clave no existe.
     * @method static bool pull(string $key, mixed $default = null) Recupera y elimina un valor del caché.
     * @method static bool forever(string $key, mixed $value) Almacena un valor en caché permanentemente.
     * @method static bool has(string $key) Verifica si una clave existe en el caché.
     * @method static bool forget(string $key) Elimina un valor del caché por su clave.
     * @method static bool flush() Limpia todo el caché.
     * @method static \Illuminate\Cache\TaggedCache tags(array $names) Accede a un caché con etiquetas para agrupar claves.
     * @method static string|null getDefaultDriver() Obtiene el driver de caché por defecto (file, redis, memcached, etc.).
     * @method static mixed driver(string|null $driver = null) Cambia o accede a un driver específico de caché.
     */
    class Cache {}

    /**
     * @mixin \Illuminate\Filesystem\FilesystemManager
     *
     * @method static \Illuminate\Filesystem\FilesystemAdapter disk(string|null $name = null) Obtiene una instancia del disco de almacenamiento (local, s3, public, etc.).
     * @method static bool put(string $path, string|\Illuminate\Http\File|\Illuminate\Http\UploadedFile $contents, mixed $options = []) Almacena un archivo en la ruta especificada.
     * @method static string get(string $path) Obtiene el contenido de un archivo.
     * @method static bool exists(string $path) Verifica si un archivo existe.
     * @method static bool delete(string|array $paths) Elimina uno o varios archivos.
     * @method static string url(string $path) Obtiene la URL pública de un archivo.
     * @method static string temporaryUrl(string $path, \DateTimeInterface $expiration, array $options = []) Genera una URL temporal firmada para un archivo privado.
     * @method static string|false putFile(string $path, string|\Illuminate\Http\File|\Illuminate\Http\UploadedFile $file, mixed $options = []) Almacena un archivo subido generando un nombre único.
     * @method static string|false putFileAs(string $path, string|\Illuminate\Http\File|\Illuminate\Http\UploadedFile $file, string $name, mixed $options = []) Almacena un archivo subido con un nombre específico.
     * @method static \Illuminate\Http\UploadedFile|false copy(string $from, string $to) Copia un archivo a una nueva ubicación.
     * @method static \Illuminate\Http\UploadedFile|false move(string $from, string $to) Mueve o renombra un archivo.
     * @method static int size(string $path) Obtiene el tamaño de un archivo en bytes.
     * @method static string mimeType(string $path) Obtiene el tipo MIME de un archivo.
     * @method static array files(string|null $directory = null, bool $recursive = false) Lista archivos en un directorio.
     * @method static array allFiles(string|null $directory = null) Lista todos los archivos recursivamente.
     * @method static array directories(string|null $directory = null) Lista subdirectorios.
     * @method static bool makeDirectory(string $path) Crea un directorio.
     * @method static bool deleteDirectory(string $directory) Elimina un directorio y todo su contenido.
     * @method static string|false checksum(string $path, array $options = []) Calcula el checksum MD5 de un archivo.
     */
    class Storage {}

    /**
     * @mixin \Illuminate\Events\Dispatcher
     *
     * @method static mixed dispatch(object|string $event, mixed $payload = [], bool $halt = false) Despacha un evento, ejecutando todos sus listeners.
     * @method static void listen(string|\Closure $events, \Closure|string|null $listener = null) Registra un listener para uno o varios eventos.
     * @method static \Illuminate\Events\Dispatcher push(string $event, mixed $payload =[]) Agrega un evento a la pila para despachar después.
     * @method static void flush(string $event) Despacha todos los eventos en la pila para un evento dado.
     * @method static void forget(string $event) Elimina todos los listeners de un evento específico.
     * @method static bool hasListeners(string $event) Verifica si un evento tiene listeners registrados.
     * @method static array getListeners(string $event) Obtiene todos los listeners registrados para un evento.
     * @method static void subscribe(object|string $subscriber) Registra un subscriber de eventos (clase con métodos handle*).
     * @method static \Closure|bool makeListener(\Closure|string $listener, bool $wildcard = false) Crea una instancia de listener.
     */
    class Event {}

    /**
     * @mixin \Illuminate\Broadcasting\BroadcastManager
     *
     * @method static mixed event(array|\Illuminate\Broadcasting\BroadcastEvent $event) Despacha un evento a los canales de broadcast (Reverb, Pusher, etc.).
     * @method static \Illuminate\Broadcasting\PendingBroadcast broadcast(mixed $event) Devuelve un PendingBroadcast para encadenar ->toOthers(), ->dontBroadcastToCurrentUser().
     * @method static \Illuminate\Broadcasting\BroadcastManager channel(string $channel, \Closure|string $callback, array $options = []) Registra un canal de broadcast con su callback de autorización.
     * @method static \Illuminate\Broadcasting\BroadcastManager routes(array|null $attributes = null) Registra las rutas de autorización de broadcast (/broadcasting/auth).
     * @method static mixed socket(\Illuminate\Http\Request|null $request = null) Obtiene el ID del socket actual para excluir al emisor con ->toOthers().
     * @method static \Illuminate\Contracts\Broadcasting\Broadcaster connection(string|null $driver = null) Obtiene una instancia del driver de broadcast (reverb, pusher, log, null).
     * @method static \Illuminate\Broadcasting\BroadcastManager driver(string|null $name = null) Cambia o accede a un driver específico.
     */
    class Broadcast {}

    /**
     * @mixin \Illuminate\Log\LogManager
     *
     * @method static void emergency(string $message, array $context = []) Registra un mensaje de log nivel EMERGENCIA (sistema inutilizable).
     * @method static void alert(string $message, array $context = []) Registra un mensaje de log nivel ALERTA (acción requerida inmediata).
     * @method static void critical(string $message, array $context = []) Registra un mensaje de log nivel CRÍTICO (componente crítico caído).
     * @method static void error(string $message, array $context = []) Registra un mensaje de log nivel ERROR (error de ejecución).
     * @method static void warning(string $message, array $context = []) Registra un mensaje de log nivel ADVERTENCIA (problema potencial).
     * @method static void notice(string $message, array $context = []) Registra un mensaje de log nivel AVISO (evento normal pero significativo).
     * @method static void info(string $message, array $context = []) Registra un mensaje de log nivel INFORMATIVO.
     * @method static void debug(string $message, array $context = []) Registra un mensaje de log nivel DEBUG (información de depuración detallada).
     * @method static void log(string $level, string $message, array $context = []) Registra un mensaje con un nivel personalizado.
     * @method static void write(string $level, string $message, array $context = []) Escribe un mensaje directamente al archivo de log sin procesamiento adicional.
     * @method static \Monolog\Logger channel(string|null $channel = null) Obtiene un canal de log específico (stack, single, daily, slack, etc.).
     * @method static array getChannels() Obtiene todos los canales de log configurados.
     * @method static \Psr\Log\LoggerInterface stack(array $channels, string|null $channel = null) Apila múltiples canales de log para escritura simultánea.
     */
    class Log {}

    /**
     * @mixin \Illuminate\Database\DatabaseManager
     *
     * @method static \Illuminate\Database\Connection connection(string|null $name = null) Obtiene una conexión a la base de datos por su nombre (mysql, pgsql, sqlite, etc.).
     * @method static void beginTransaction() Inicia una transacción de base de datos. Todas las operaciones posteriores son temporales hasta commit/rollback.
     * @method static void commit() Confirma la transacción activa, haciendo permanentes los cambios.
     * @method static void rollBack(int|null $toLevel = null) Revierte la transacción activa, descartando todos los cambios desde beginTransaction.
     * @method static mixed transaction(\Closure $callback, int $attempts = 1) Ejecuta un callback dentro de una transacción. Reintenta automáticamente si hay deadlock.
     * @method static void afterCommit(\Closure $callback) Registra un callback que se ejecuta después de que la transacción actual se confirme.
     * @method static mixed select(string $query, array $bindings = [], bool $useReadPdo = true) Ejecuta una consulta SELECT directa y retorna los resultados.
     * @method static int insert(string $query, array $bindings = []) Ejecuta una consulta INSERT directa y retorna el número de filas afectadas.
     * @method static int update(string $query, array $bindings = []) Ejecuta una consulta UPDATE directa y retorna el número de filas afectadas.
     * @method static int delete(string $query, array $bindings = []) Ejecuta una consulta DELETE directa y retorna el número de filas afectadas.
     * @method static mixed unprepared(string $query) Ejecuta una consulta SQL sin preparar (sin bindings).
     * @method static int affectingStatement(string $query, array $bindings = []) Ejecuta una sentencia y retorna el número de filas afectadas.
     * @method static \PDOStatement cursor(string $query, array $bindings = [], bool $useReadPdo = true) Ejecuta una consulta y retorna un cursor para iterar sobre resultados sin cargar todo en memoria.
     * @method static \Illuminate\Database\Schema\Builder getSchemaBuilder() Obtiene el constructor de esquemas para la conexión actual.
     * @method static \Illuminate\Database\Query\Builder table(string $table) Obtiene un query builder para la tabla especificada.
     * @method static string getDriverName() Obtiene el nombre del driver de BD (mysql, pgsql, sqlite).
     * @method static void purge(string|null $name = null) Elimina la conexión de la configuración activa.
     * @method static void disconnect(string|null $name = null) Desconecta la conexión actual (útil para pruebas).
     * @method static void reconnect(string|null $name = null) Reconecta forzadamente la conexión.
     */
    class DB {}

    /**
     * @mixin \Illuminate\Routing\ResponseFactory
     *
     * @method static \Illuminate\Http\JsonResponse json(mixed $data = [], int $status = 200, array $headers = [], int $options = 0) Retorna una respuesta JSON. $options para JSON_UNESCAPED_UNICODE, etc.
     * @method static \Illuminate\Http\Response noContent(int $status = 204, array $headers = []) Retorna una respuesta vacía con código 204 (útil para DELETE).
     * @method static \Illuminate\Http\JsonResponse created(string|null $route = null, mixed $data = null) Retorna una respuesta 201 Created con ubicación opcional.
     * @method static \Illuminate\Http\JsonResponse accepted(mixed $data = null, string|null $location = null) Retorna una respuesta 202 Accepted.
     * @method static \Illuminate\Http\Response view(string $view, array $data = [], int $status = 200, array $headers = []) Retorna una respuesta renderizada de Blade.
     * @method static \Illuminate\Http\RedirectResponse redirectTo(string $path, int $status = 302, array $headers = [], bool|null $secure = null) Redirige a una ruta interna.
     * @method static \Illuminate\Http\RedirectResponse redirectToRoute(string $route, mixed $parameters = [], int $status = 302, array $headers = []) Redirige a una ruta nombrada.
     * @method static \Illuminate\Http\RedirectResponse redirectToAction(string $action, mixed $parameters = [], int $status = 302, array $headers = []) Redirige a un método de controlador.
     * @method static \Illuminate\Http\RedirectResponse redirectGuest(string $path, int $status = 302, array $headers = [], bool|null $secure = null) Redirige a la URL de invitado configurada.
     * @method static \Illuminate\Http\RedirectResponse redirectToIntended(string $default, int $status = 302, array $headers = []) Redirige a la URL previa al login (almacenada en sesión).
     * @method static \Symfony\Component\HttpFoundation\BinaryFileResponse download(\SplFileInfo|string $file, string|null $name = null, array $headers = []) Fuerza la descarga de un archivo.
     * @method static \Symfony\Component\HttpFoundation\BinaryFileResponse file(\SplFileInfo|string $file, array $headers = []) Muestra un archivo en el navegador (inline).
     * @method static \Illuminate\Http\Response stream(callable $callback, int $status = 200, array $headers = []) Transmite una respuesta como stream.
     * @method static \Illuminate\Http\Response streamDownload(callable $callback, string|null $name = null, array $headers = []) Transmite una descarga como stream.
     * @method static \Illuminate\Http\Response make(mixed $content = '', int $status = 200, array $headers = []) Crea una respuesta HTTP personalizada.
     */
    class Response {}
}

namespace Illuminate\Routing {

    /**
     * @mixin \Illuminate\Routing\RouteGroup
     *
     * @method static \Illuminate\Routing\Route get(string $uri, array|string|callable|null $action = null) Registra una ruta que responde a GET.
     * @method static \Illuminate\Routing\Route post(string $uri, array|string|callable|null $action = null) Registra una ruta que responde a POST.
     * @method static \Illuminate\Routing\Route put(string $uri, array|string|callable|null $action = null) Registra una ruta que responde a PUT.
     * @method static \Illuminate\Routing\Route patch(string $uri, array|string|callable|null $action = null) Registra una ruta que responde a PATCH.
     * @method static \Illuminate\Routing\Route delete(string $uri, array|string|callable|null $action = null) Registra una ruta que responde a DELETE.
     * @method static \Illuminate\Routing\Route options(string $uri, array|string|callable|null $action = null) Registra una ruta que responde a OPTIONS.
     * @method static \Illuminate\Routing\Route match(array|string $methods, string $uri, array|string|callable|null $action = null) Registra una ruta que responde a uno o varios métodos HTTP.
     * @method static \Illuminate\Routing\Route view(string $uri, string $view, array $data = [], int|array $status = 200, array $headers = []) Registra una ruta que retorna directamente una vista.
     * @method static \Illuminate\Routing\Route redirect(string $uri, string $destination, int $status = 302) Registra una ruta que redirige a otra URI.
     * @method static \Illuminate\Routing\Route permanentRedirect(string $uri, string $destination) Registra una redirección permanente (301).
     * @method static \Illuminate\Routing\RouteRegistrar middleware(string|array $middleware) Asigna middleware a un grupo de rutas.
     * @method static \Illuminate\Routing\RouteRegistrar prefix(string $prefix) Agrega un prefijo a todas las rutas del grupo.
     * @method static \Illuminate\Routing\RouteRegistrar name(string $name) Agrega un nombre base a todas las rutas del grupo.
     * @method static \Illuminate\Routing\RouteRegistrar controller(string $controller) Asigna un controlador base a un grupo de rutas.
     * @method static \Illuminate\Routing\RouteRegistrar domain(string $domain) Restringe las rutas a un subdominio específico.
     * @method static \Illuminate\Routing\RouteRegistrar where(array $pattern) Agrega restricciones de formato a parámetros de ruta.
     * @method static \Illuminate\Routing\RouteRegistrar withoutMiddleware(string|array $middleware) Omite middleware específico para el grupo de rutas.
     * @method static void group(\Closure|array $attributesOrCallback, \Closure|string|null $callback = null) Agrupa rutas compartiendo atributos (middleware, prefix, name, etc.).
     * @method static \Illuminate\Routing\Route resource(string $name, string $controller, array $options = []) Registra rutas CRUD completas para un recurso (index, create, store, show, edit, update, destroy).
     * @method static void apiResource(string $name, string $controller, array $options = []) Registra rutas CRUD de API (sin create/edit) para un recurso.
     * @method static \Illuminate\Routing\Route current() Obtiene la ruta que coincide con la solicitud actual.
     * @method static string|null currentRouteName() Obtiene el nombre de la ruta actual.
     * @method static string|null currentRouteAction() Obtiene el controlador y método de la ruta actual.
     * @method static bool has(string $name) Verifica si existe una ruta registrada con el nombre dado.
     */
    class Route {}
}

namespace Illuminate\Http {

    /**
     * @mixin \Illuminate\Http\Request
     *
     * @method static \Illuminate\Validation\Validator|array validate(array $rules, ...$params) Valida la solicitud contra reglas. Lanza ValidationException si falla (redirección o respuesta JSON).
     * @method static mixed input(string|null $key = null, mixed $default = null) Obtiene un valor de entrada (query string + formulario).
     * @method static string|null query(string|null $key = null, mixed $default = null) Obtiene un valor de la query string (?key=value).
     * @method static mixed post(string|null $key = null, mixed $default = null) Obtiene un valor del body POST.
     * @method static mixed all(array|mixed|null $keys = null) Obtiene todos los datos de entrada de la solicitud.
     * @method static array only(array|mixed $keys) Obtiene solo los campos especificados de la solicitud.
     * @method static array except(array|mixed $keys) Obtiene todos los campos excepto los especificados.
     * @method static bool has(string|array $key) Verifica si un campo existe en la solicitud.
     * @method static bool filled(string|array $key) Verifica si un campo existe y no está vacío.
     * @method static bool missing(string|array $key) Verifica si un campo NO existe en la solicitud.
     * @method static string|null bearerToken() Obtiene el token Bearer del header Authorization (útil para Sanctum).
     * @method static \App\Models\User|null user(string|null $guard = null) Obtiene el usuario autenticado para esta solicitud.
     * @method static string|int|null session(string|null $guard = null) Obtiene el ID del usuario autenticado.
     * @method static string method() Obtiene el método HTTP de la solicitud (GET, POST, PUT, PATCH, DELETE).
     * @method static string path() Obtiene la ruta de la solicitud sin el dominio (/api/clubs/{club}).
     * @method static string url() Obtiene la URL completa sin query string.
     * @method static string fullUrl() Obtiene la URL completa con query string.
     * @method static string ip() Obtiene la dirección IP del cliente.
     * @method static string header(string $key, string|null $default = null) Obtiene un header de la solicitud.
     * @method static bool expectsJson() Verifica si la solicitud espera una respuesta JSON (header Accept: application/json).
     * @method static bool wantsJson() Verifica si la solicitud acepta JSON (header Accept contiene application/json).
     * @method static bool ajax() Verifica si la solicitud es AJAX (header X-Requested-With: XMLHttpRequest).
     * @method static \Illuminate\Http\UploadedFile|null file(string|null $key = null, mixed $default = null) Obtiene un archivo subido.
     * @method static bool hasFile(string $key) Verifica si la solicitud contiene un archivo subido.
     * @method static bool isMethod(string $method) Verifica si el método HTTP coincide (isMethod('post'), isMethod('delete')).
     * @method static string|null fingerprint() Obtiene el fingerprint único de la solicitud (basado en ruta + método + IP).
     * @method static bool secure() Verifica si la solicitud fue mediante HTTPS.
     * @method static \Illuminate\Support\Carbon|null date(string $key, string|null $format = null, string|null $tz = null) Obtiene un campo de fecha como instancia Carbon.
     * @method static bool boolean(string|null $key = null, bool $default = false) Obtiene un campo como booleano (true para "1", "true", "on", "yes").
     * @method static \Illuminate\Support\Stringable str(string $key, mixed $default = null) Obtiene un campo como objeto Stringable con métodos fluentes de string.
     * @method static float|int integer(string $key, float|int|null $default = null) Obtiene un campo como entero.
     * @method static float float(string $key, float|null $default = null) Obtiene un campo como flotante.
     * @method static array collect(array|null $keys = null) Obtiene los datos de entrada como colección Laravel (Collection).
     */
    class Request {}
}

namespace Illuminate\Support\Facades {

    /**
     * @mixin \Illuminate\Config\Repository
     *
     * @method static mixed get(string $key, mixed $default = null) Obtiene un valor de configuración usando notación de punto ('app.name', 'database.connections.mysql.host').
     * @method static bool has(string $key) Verifica si una clave de configuración existe.
     * @method static void set(string $key, mixed $value = null) Establece un valor de configuración en tiempo de ejecución.
     * @method static array all() Obtiene toda la configuración como array.
     */
    class Config {}

    /**
     * @mixin \Illuminate\Encryption\Encrypter
     *
     * @method static string encrypt(mixed $value, bool $serialize = true) Encripta un valor usando la clave APP_KEY.
     * @method static mixed decrypt(string $payload, bool $unserialize = true) Desencripta un payload previamente encriptado.
     * @method static string encryptString(string $value) Encripta un string sin serializar.
     * @method static string decryptString(string $payload) Desencripta un string sin deserializar.
     * @method static string generateKey(string $cipher) Genera una nueva clave de encriptación para el cifrado especificado (AES-128-CBC, AES-256-CBC).
     */
    class Crypt {}

    /**
     * @mixin \Illuminate\Mail\Mailer
     *
     * @method static \Illuminate\Mail\SentMessage|null to(\Illuminate\Contracts\Mail\Mailable|\Closure|string|array $users, string|null $name = null) Establece el destinatario "Para" del correo.
     * @method static \Illuminate\Mail\Mailable bcc(\Illuminate\Contracts\Mail\Mailable|\Closure|string|array $users, string|null $name = null) Establece el destinatario "CCO" del correo.
     * @method static \Illuminate\Mail\Mailable cc(\Illuminate\Contracts\Mail\Mailable|\Closure|string|array $users, string|null $name = null) Establece el destinatario "CC" del correo.
     * @method static \Illuminate\Mail\Mailable raw(string $text, mixed $callback) Envía un correo con contenido en texto plano.
     * @method static \Illuminate\Mail\Mailable html(string $html, mixed $callback) Envía un correo con contenido HTML directo.
     * @method static \Illuminate\Mail\Mailable send(\Illuminate\Contracts\Mail\Mailable|string|array $mailable, \Closure|string|null $callback = null) Envía un correo usando una clase Mailable.
     * @method static void queue(\Illuminate\Contracts\Mail\Mailable|string|array $mailable, string|null $queue = null) Encola un correo para envío asíncrono.
     * @method static void later(\DateTimeInterface|\DateInterval|int $delay, \Illuminate\Contracts\Mail\Mailable|string|array $mailable, string|null $queue = null) Encola un correo con retraso para envío asíncrono.
     * @method static \Illuminate\Mail\MailManager driver(string|null $driver = null) Cambia el driver de correo (smtp, mailgun, postmark, ses, log, array).
     * @method static array failures() Obtiene las direcciones de correo que fallaron en el último envío.
     * @method static \Illuminate\Mail\Mailer alwaysFrom(string $address, string|null $name = null) Configura la dirección "De" global para todos los correos.
     * @method static \Illuminate\Mail\Mailer alwaysReplyTo(string $address, string|null $name = null) Configura la dirección "Responder a" global.
     * @method static \Illuminate\Mail\Mailer alwaysTo(string $address, string|null $name = null) Configura la dirección "Para" global (útil para entornos de staging/pruebas).
     */
    class Mail {}

    /**
     * @mixin \Illuminate\Queue\QueueManager
     *
     * @method static \Illuminate\Contracts\Queue\Job|null pop(string|null $queue = null) Extrae el siguiente trabajo de la cola especificada.
     * @method static mixed push(string|object $job, mixed $data = '', string|null $queue = null) Agrega un trabajo a la cola para procesamiento asíncrono.
     * @method static mixed pushOn(string $queue, string|object $job, mixed $data = '') Agrega un trabajo a una cola específica.
     * @method static mixed later(\DateTimeInterface|\DateInterval|int $delay, string|object $job, mixed $data = '', string|null $queue = null) Agrega un trabajo a la cola con retraso.
     * @method static mixed bulk(array $jobs, mixed $data = '', string|null $queue = null) Agrega múltiples trabajos a la cola.
     * @method static \Illuminate\Queue\QueueManager connection(string|null $name = null) Cambia o accede a una conexión de cola específica (redis, database, sqs, sync).
     * @method static string getDefaultDriver() Obtiene el driver de cola por defecto.
     * @method static void setDefaultDriver(string $name) Cambia el driver de cola por defecto en tiempo de ejecución.
     * @method static \Illuminate\Contracts\Queue\Queue driver(string|null $name = null) Obtiene el driver de cola como instancia Queue.
     * @method static void assertNothingPushed() Afirma que no se encoló ningún trabajo (útil en tests).
     * @method static void assertPushed(string|\Closure $job, callable|null $callback = null) Afirma que un trabajo fue encolado (útil en tests).
     * @method static void assertNotPushed(string|\Closure $job, callable|null $callback = null) Afirma que un trabajo NO fue encolado (útil en tests).
     * @method static void assertPushedOn(string $queue, string|\Closure $job, callable|null $callback = null) Afirma que un trabajo fue encolado en una cola específica (tests).
     * @method static void assertCount(int $count) Afirma el número total de trabajos encolados (tests).
     */
    class Queue {}

    /**
     * @mixin \Illuminate\Bus\Dispatcher
     *
     * @method static mixed dispatch(mixed $command) Despacha un comando/job para ejecución inmediata (síncrona o encolada según configuración).
     * @method static mixed dispatchSync(mixed $command) Despacha un comando para ejecución síncrona inmediata (sin cola).
     * @method static mixed dispatchNow(mixed $command) Alias de dispatchSync. Despacha ejecución inmediata.
     * @method static mixed dispatchToQueue(mixed $command) Despacha un comando para ejecución asíncrona (siempre a la cola, ignorando config sync).
     * @method static void assertDispatched(string|\Closure $command, callable|null $callback = null) Afirma que un comando fue despachado (tests).
     * @method static void assertNotDispatched(string|\Closure $command, callable|null $callback = null) Afirma que un comando NO fue despachado (tests).
     * @method static void assertDispatchedSync(string|\Closure $command, callable|null $callback = null) Afirma que un comando fue despachado síncronamente (tests).
     * @method static void assertDispatchedAfterResponse(string|\Closure $command, callable|null $callback = null) Afirma que un comando fue despachado post-respuesta (tests).
     * @method static void pipeThrough(array $pipes) Establece pipes de middleware para los comandos.
     * @method static \Illuminate\Bus\Dispatcher map(array $map) Mapea comandos a sus handlers (útil para Command Bus).
     */
    class Bus {}

    /**
     * @mixin \Illuminate\Pipeline\Pipeline
     *
     * @method static \Illuminate\Pipeline\Pipeline send(mixed $passable) Establece el objeto que viajará a través de los pipes.
     * @method static \Illuminate\Pipeline\Pipeline through(array|mixed $pipes) Establece los pipes (clases, funciones) por los que pasará el objeto.
     * @method static mixed then(\Closure $destination) Ejecuta el pipeline y pasa el resultado al callback final.
     * @method static mixed thenReturn() Ejecuta el pipeline y retorna el resultado directamente.
     * @method static \Illuminate\Pipeline\Pipeline via(string $method) Cambia el método invocado en cada pipe (por defecto 'handle').
     */
    class Pipeline {}

    /**
     * @mixin \Illuminate\Redis\RedisManager
     *
     * @method static mixed command(string $method, array $parameters = []) Ejecuta un comando de Redis directamente (SET, GET, PUBLISH, SUBSCRIBE, etc).
     * @method static mixed connection(string|null $name = null) Obtiene una conexión Redis por su nombre (default, cache, etc.).
     * @method static \Illuminate\Redis\Connections\PhpRedisConnection|\Illuminate\Redis\Connections\PredisConnection driver(string|null $driver = null) Cambia o accede al driver Redis (phpredis, predis).
     * @method static string getDefaultDriver() Obtiene el driver Redis por defecto.
     * @method static \Illuminate\Redis\RedisManager enableEvents() Habilita eventos de Redis (útil para broadcasting con Reverb).
     * @method static \Illuminate\Redis\RedisManager disableEvents() Deshabilita eventos de Redis.
     */
    class Redis {}

    /**
     * @mixin \Illuminate\Notifications\ChannelManager
     *
     * @method static void send(\Illuminate\Contracts\Notifications\ShouldQueue|\Illuminate\Contracts\Notifications\AnonymousNotifiable|\Illuminate\Contracts\Notifications\Notification $notifiables, \Illuminate\Contracts\Notifications\Notification $notification) Envía una notificación a uno o varios destinatarios.
     * @method static void sendNow(\Illuminate\Contracts\Notifications\ShouldQueue|\Illuminate\Contracts\Notifications\AnonymousNotifiable|\Illuminate\Contracts\Notifications\Notification $notifiables, \Illuminate\Contracts\Notifications\Notification $notification) Envía una notificación inmediatamente (sin cola).
     * @method static \Illuminate\Notifications\ChannelManager channel(string|null $name = null) Obtiene un canal de notificación específico (mail, database, broadcast, vonage, slack).
     * @method static mixed driver(string|null $driver = null) Alias de channel(). Obtiene un driver de notificación.
     * @method static \Illuminate\Notifications\ChannelManager deliverVia(string $channel) Establece el canal por defecto para entrega.
     * @method static string|null deliversVia() Obtiene el canal de entrega por defecto.
     * @method static void assertSent(mixed $notifiable, string|\Closure $notification, callable|null $callback = null) Afirma que una notificación fue enviada (tests).
     * @method static void assertNotSent(mixed $notifiable, string|\Closure $notification, callable|null $callback = null) Afirma que una notificación NO fue enviada (tests).
     * @method static void assertNothingSent() Afirma que no se enviaron notificaciones (tests).
     * @method static void assertCount(int $count) Afirma la cantidad de notificaciones enviadas (tests).
     */
    class Notification {}

    /**
     * @mixin \Illuminate\Filesystem\Filesystem
     *
     * @method static bool exists(string $path) Verifica si un archivo existe en el sistema de archivos local.
     * @method static string get(string $path) Obtiene el contenido de un archivo.
     * @method static bool put(string $path, string $contents, bool $lock = false) Escribe contenido en un archivo.
     * @method static bool prepend(string $path, string $data) Agrega contenido al inicio de un archivo existente.
     * @method static bool append(string $path, string $data) Agrega contenido al final de un archivo existente.
     * @method static bool delete(string|array $paths) Elimina uno o varios archivos.
     * @method static bool copy(string $from, string $to) Copia un archivo.
     * @method static bool move(string $from, string $to) Mueve o renombra un archivo.
     * @method static string basename(string $path) Obtiene el nombre base del archivo (sin ruta).
     * @method static string dirname(string $path) Obtiene el directorio padre de un archivo.
     * @method static string extension(string $path) Obtiene la extensión del archivo.
     * @method static string type(string $path) Obtiene el tipo de archivo (file, dir, link).
     * @method static int size(string $path) Obtiene el tamaño del archivo en bytes.
     * @method static int lastModified(string $path) Obtiene el timestamp de última modificación.
     * @method static bool isDirectory(string $directory) Verifica si la ruta es un directorio.
     * @method static bool isFile(string $file) Verifica si la ruta es un archivo.
     * @method static bool isWritable(string $path) Verifica si el archivo o directorio tiene permisos de escritura.
     * @method static bool makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false) Crea un directorio.
     * @method static void ensureDirectoryExists(string $path, int $mode = 0755, bool $recursive = true) Asegura que un directorio exista, creándolo si es necesario.
     * @method static array directories(string $directory) Obtiene los subdirectorios dentro de un directorio.
     * @method static array files(string $directory) Obtiene los archivos dentro de un directorio.
     * @method static array glob(string $pattern, int $flags = 0) Busca archivos usando un patrón glob.
     * @method static string hash(string $path) Obtiene el hash MD5 de un archivo.
     * @method static \Symfony\Component\Finder\SplFileInfo[] allFiles(string $directory, bool $hidden = false) Obtiene todos los archivos recursivamente.
     * @method static void requireOnce(string $file) Incluye un archivo PHP una sola vez.
     * @method static string getRequire(string $file, array $data = []) Incluye un archivo PHP y captura su retorno.
     */
    class File {}
}

namespace Illuminate\Auth\Access {

    /**
     * @mixin \Illuminate\Auth\Access\Gate
     *
     * @method static bool authorize(string $ability, array|mixed $arguments = []) Autoriza una habilidad. Lanza AuthorizationException si no tiene permiso.
     * @method static \Illuminate\Auth\Access\Response authorize(string $ability, array|mixed $arguments = []) Retorna una Response, útil cuando no quieres excepción automática.
     * @method static bool allows(string $ability, array|mixed $arguments = []) Verifica si una habilidad está permitida (retorna bool, no lanza excepción).
     * @method static bool denies(string $ability, array|mixed $arguments = []) Verifica si una habilidad está denegada (retorna bool).
     * @method static bool check(array|string $abilities, array|mixed $arguments = []) Verifica si todas las habilidades especificadas están permitidas.
     * @method static bool any(array|string $abilities, array|mixed $arguments = []) Verifica si ALGUNA de las habilidades está permitida.
     * @method static bool none(array|string $abilities, array|mixed $arguments = []) Verifica si NINGUNA de las habilidades está permitida.
     * @method static \Illuminate\Auth\Access\Gate before(callable $callback) Registra un callback que se ejecuta ANTES de todas las autorizaciones (útil para admin/superadmin).
     * @method static \Illuminate\Auth\Access\Gate after(callable $callback) Registra un callback que se ejecuta DESPUÉS de todas las autorizaciones.
     * @method static \Illuminate\Auth\Access\Gate define(string $ability, callable|string $callback) Define una habilidad de autorización con un callback.
     * @method static \Illuminate\Auth\Access\Gate policy(string $class, string $policy) Asigna una policy class a un modelo (User::class => UserPolicy::class).
     * @method static \Illuminate\Auth\Access\Gate guessPolicyNamesUsing(callable $callback) Personaliza cómo se resuelve la policy class para un modelo.
     * @method static \Illuminate\Auth\Access\Gate resource(string $name, string $class, array|null $abilities = null) Define habilidades CRUD para un recurso con sus policies.
     * @method static \Illuminate\Auth\Access\Gate forUser(\Illuminate\Contracts\Auth\Authenticatable|mixed $user) Crea una instancia Gate para un usuario específico (útil en comandos/eventos).
     * @method static mixed getPolicyFor(object|string $class) Obtiene la policy asociada a una clase o instancia.
     */
    class Gate {}
}

namespace Illuminate\Support {

    /**
     * @mixin \Illuminate\Support\Collection
     *
     * @method static \Illuminate\Support\Collection all() Retorna el array subyacente de la colección.
     * @method static \Illuminate\Support\Collection average(string|null $callback = null) Alias de avg(). Calcula el promedio.
     * @method static \Illuminate\Support\Collection avg(string|null $callback = null) Calcula el promedio de valores.
     * @method static \Illuminate\Support\Collection chunk(int $size) Divide la colección en grupos de tamaño fijo.
     * @method static \Illuminate\Support\Collection collapse() Colapsa una colección de arrays en una colección plana.
     * @method static \Illuminate\Support\Collection combine(mixed $values) Combina los valores de la colección como claves con otra colección como valores.
     * @method static \Illuminate\Support\Collection concat(\Illuminate\Support\Collection|\Illuminate\Contracts\Support\Arrayable|array $source) Agrega elementos de otra colección/array al final.
     * @method static \Illuminate\Support\Collection contains(mixed $key, mixed $operator = null, mixed $value = null) Verifica si la colección contiene un elemento.
     * @method static \Illuminate\Support\Collection count() Retorna el número total de elementos.
     * @method static \Illuminate\Support\Collection countBy(callable|null $countBy = null) Cuenta las ocurrencias de cada valor en la colección.
     * @method static \Illuminate\Support\Collection crossJoin(mixed ...$arrays) Calcula el producto cruzado con una o más colecciones.
     * @method static \Illuminate\Support\Collection dd() Muestra y muere (dump & die) con los elementos de la colección.
     * @method static \Illuminate\Support\Collection diff(mixed $items) Retorna elementos presentes en la colección pero no en la otra.
     * @method static \Illuminate\Support\Collection diffAssoc(mixed $items) Retorna diferencias basadas en clave-valor.
     * @method static \Illuminate\Support\Collection diffKeys(mixed $items) Retorna elementos cuyas claves no están en la otra colección.
     * @method static \Illuminate\Support\Collection dump() Muestra los elementos de la colección sin morir.
     * @method static \Illuminate\Support\Collection duplicates(string|null $column = null) Retorna valores duplicados.
     * @method static \Illuminate\Support\Collection each(callable $callback) Itera sobre los elementos. Retorna la colección original.
     * @method static \Illuminate\Support\Collection eachSpread(callable $callback) Itera sobre elementos que son arrays y los pasa como argumentos separados.
     * @method static \Illuminate\Support\Collection every(callable $callback) Retorna true si todos los elementos pasan el callback de verificación.
     * @method static \Illuminate\Support\Collection except(mixed $keys) Retorna todos los elementos excepto los de las claves especificadas.
     * @method static \Illuminate\Support\Collection filter(callable|null $callback = null) Filtra la colección usando un callback.
     * @method static \Illuminate\Support\Collection first(callable|null $callback = null, mixed $default = null) Retorna el primer elemento que pasa el callback.
     * @method static \Illuminate\Support\Collection firstWhere(string $key, mixed $operator = null, mixed $value = null) Retorna el primer elemento donde la clave cumple la condición.
     * @method static \Illuminate\Support\Collection flatMap(callable $callback) Mapea cada elemento a un array y aplana el resultado.
     * @method static \Illuminate\Support\Collection flatten(float $depth = INF) Aplana una colección multidimensional.
     * @method static \Illuminate\Support\Collection flip() Intercambia claves y valores.
     * @method static \Illuminate\Support\Collection forget(mixed $keys) Elimina elementos por sus claves.
     * @method static \Illuminate\Support\Collection forPage(int $page, int $perPage) Retorna los elementos para una página específica (paginación).
     * @method static \Illuminate\Support\Collection get(mixed $key, mixed $default = null) Obtiene un elemento por su clave.
     * @method static \Illuminate\Support\Collection groupBy(callable|string $groupBy, bool $preserveKeys = false) Agrupa elementos por una clave o callback.
     * @method static \Illuminate\Support\Collection has(mixed $key) Verifica si una clave existe.
     * @method static \Illuminate\Support\Collection implode(string $value, string|null $glue = null) Une elementos en un string.
     * @method static \Illuminate\Support\Collection intersect(mixed $items) Retorna elementos presentes en ambas colecciones.
     * @method static \Illuminate\Support\Collection isEmpty() Verifica si la colección está vacía.
     * @method static \Illuminate\Support\Collection isNotEmpty() Verifica si la colección NO está vacía.
     * @method static \Illuminate\Support\Collection join(string $glue, string|null $finalGlue = null) Une elementos con un separador, con separador final opcional.
     * @method static \Illuminate\Support\Collection keyBy(callable|string $keyBy) Reindexa la colección usando una clave.
     * @method static \Illuminate\Support\Collection keys() Retorna todas las claves de la colección.
     * @method static \Illuminate\Support\Collection last(callable|null $callback = null, mixed $default = null) Retorna el último elemento que pasa el callback.
     * @method static \Illuminate\Support\Collection macro(string $name, callable $macro) Registra un método personalizado en la clase Collection.
     * @method static \Illuminate\Support\Collection make(mixed $items = []) Crea una nueva instancia Collection.
     * @method static \Illuminate\Support\Collection map(callable $callback) Transforma cada elemento usando un callback.
     * @method static \Illuminate\Support\Collection mapInto(string $class) Crea nuevas instancias de una clase usando cada elemento como argumento.
     * @method static \Illuminate\Support\Collection mapSpread(callable $callback) Mapea elementos que son arrays pasándolos como argumentos separados.
     * @method static \Illuminate\Support\Collection mapToGroups(callable $callback) Agrupa elementos por un callback retornando clave => grupo.
     * @method static \Illuminate\Support\Collection mapWithKeys(callable $callback) Mapea elementos retornando clave => valor.
     * @method static \Illuminate\Support\Collection max(callable|string|null $callback = null) Retorna el valor máximo.
     * @method static \Illuminate\Support\Collection median(string|null $key = null) Retorna la mediana.
     * @method static \Illuminate\Support\Collection merge(mixed $items) Fusiona otra colección/array sobrescribiendo claves existentes.
     * @method static \Illuminate\Support\Collection mergeRecursive(mixed $items) Fusiona recursivamente.
     * @method static \Illuminate\Support\Collection min(callable|string|null $callback = null) Retorna el valor mínimo.
     * @method static \Illuminate\Support\Collection mode(string|null $key = null) Retorna la moda.
     * @method static \Illuminate\Support\Collection nth(int $step, int $offset = 0) Retorna cada n-ésimo elemento.
     * @method static \Illuminate\Support\Collection only(mixed $keys) Retorna solo los elementos con las claves especificadas.
     * @method static \Illuminate\Support\Collection pad(int $size, mixed $value) Rellena la colección al tamaño especificado.
     * @method static \Illuminate\Support\Collection partition(callable $callback) Parte la colección en dos: los que pasan y los que no.
     * @method static \Illuminate\Support\Collection pipe(callable $callback) Pasa la colección a un callback y retorna el resultado.
     * @method static \Illuminate\Support\Collection pipeInto(string $class) Pasa la colección al constructor de una clase.
     * @method static \Illuminate\Support\Collection pluck(mixed $value, string|null $key = null) Obtiene una columna de valores (similar a array_column).
     * @method static \Illuminate\Support\Collection pop() Retorna y elimina el último elemento.
     * @method static \Illuminate\Support\Collection prepend(mixed $value, mixed $key = null) Agrega un elemento al inicio.
     * @method static \Illuminate\Support\Collection pull(mixed $key, mixed $default = null) Retorna y elimina un elemento por su clave.
     * @method static \Illuminate\Support\Collection push(mixed ...$values) Agrega uno o más elementos al final.
     * @method static \Illuminate\Support\Collection put(mixed $key, mixed $value) Establece un elemento con clave y valor.
     * @method static \Illuminate\Support\Collection random(int|null $number = null) Obtiene uno o más elementos aleatorios.
     * @method static \Illuminate\Support\Collection range(mixed $from, mixed $to) Crea una colección con un rango de valores (similar a range()).
     * @method static \Illuminate\Support\Collection reduce(callable $callback, mixed $initial = null) Reduce la colección a un solo valor con un callback acumulador.
     * @method static \Illuminate\Support\Collection reject(callable $callback) Filtra elementos usando un callback (inverso de filter).
     * @method static \Illuminate\Support\Collection replace(mixed $items) Reemplaza elementos por sus claves.
     * @method static \Illuminate\Support\Collection replaceRecursive(mixed $items) Reemplaza recursivamente.
     * @method static \Illuminate\Support\Collection reverse() Invierte el orden de los elementos.
     * @method static \Illuminate\Support\Collection search(mixed $value, bool $strict = false) Busca un valor y retorna su clave.
     * @method static \Illuminate\Support\Collection shift() Retorna y elimina el primer elemento.
     * @method static \Illuminate\Support\Collection shuffle(int|null $seed = null) Mezcla aleatoriamente los elementos.
     * @method static \Illuminate\Support\Collection skip(int $count) Salta los primeros n elementos.
     * @method static \Illuminate\Support\Collection skipUntil(mixed $value|callable) Salta elementos hasta que el callback retorne true.
     * @method static \Illuminate\Support\Collection skipWhile(mixed $value|callable) Salta elementos mientras el callback retorne true.
     * @method static \Illuminate\Support\Collection slice(int $offset, int|null $length = null) Extrae una porción de la colección.
     * @method static \Illuminate\Support\Collection sliding(int $size = 2, int $step = 1) Retorna ventanas deslizantes de la colección.
     * @method static \Illuminate\Support\Collection sole(mixed $key, mixed $operator = null, mixed $value = null) Retorna el único elemento que cumple la condición; lanza excepción si hay 0 o más de 1.
     * @method static \Illuminate\Support\Collection some(mixed $key, mixed $operator = null, mixed $value = null) Alias de contains().
     * @method static \Illuminate\Support\Collection sort(callable|null $callback = null) Ordena la colección con un callback.
     * @method static \Illuminate\Support\Collection sortBy(mixed $callback, int $options = SORT_REGULAR, bool $descending = false) Ordena por una clave o callback.
     * @method static \Illuminate\Support\Collection sortByDesc(mixed $callback, int $options = SORT_REGULAR) Ordena descendente por clave.
     * @method static \Illuminate\Support\Collection sortDesc(int $options = SORT_REGULAR) Ordena descendente.
     * @method static \Illuminate\Support\Collection sortKeys(int $options = SORT_REGULAR, bool $descending = false) Ordena por claves.
     * @method static \Illuminate\Support\Collection sortKeysDesc(int $options = SORT_REGULAR) Ordena por claves descendente.
     * @method static \Illuminate\Support\Collection splice(int $offset, int|null $length = null, mixed $replacement = []) Elimina y retorna una porción, opcionalmente la reemplaza.
     * @method static \Illuminate\Support\Collection split(int $numberOfGroups) Divide la colección en N grupos del mismo tamaño.
     * @method static \Illuminate\Support\Collection splitIn(int $numberOfGroups) Divide en N grupos intentando balancear el tamaño.
     * @method static \Illuminate\Support\Collection sum(callable|string|null $callback = null) Suma los valores de la colección.
     * @method static \Illuminate\Support\Collection take(int $limit) Toma los primeros n elementos.
     * @method static \Illuminate\Support\Collection takeUntil(mixed $value|callable) Toma elementos hasta que el callback retorne true.
     * @method static \Illuminate\Support\Collection takeWhile(mixed $value|callable) Toma elementos mientras el callback retorne true.
     * @method static \Illuminate\Support\Collection tap(callable $callback) Pasa la colección a un callback sin modificar la colección.
     * @method static \Illuminate\Support\Collection times(int $number, callable|null $callback = null) Crea una colección repitiendo un callback N veces.
     * @method static \Illuminate\Support\Collection toArray() Convierte la colección a un array PHP.
     * @method static \Illuminate\Support\Collection toJson(int $options = 0) Convierte la colección a JSON.
     * @method static \Illuminate\Support\Collection transform(callable $callback) Transforma cada elemento (modifica la colección original).
     * @method static \Illuminate\Support\Collection union(mixed $items) Agrega elementos que no tengan claves existentes.
     * @method static \Illuminate\Support\Collection unique(string|null $key = null, bool $strict = false) Retorna elementos únicos.
     * @method static \Illuminate\Support\Collection unless(mixed $condition, callable $callback, callable|null $default = null) Ejecuta callback si la condición es false.
     * @method static \Illuminate\Support\Collection unlessEmpty(callable $callback) Ejecuta callback si la colección no está vacía.
     * @method static \Illuminate\Support\Collection unlessNotEmpty(callable $callback) Ejecuta callback si la colección está vacía.
     * @method static \Illuminate\Support\Collection unwrap(mixed $value) Retorna el valor subyacente de una Collection o array.
     * @method static \Illuminate\Support\Collection values() Retorna los valores reindexando numéricamente.
     * @method static \Illuminate\Support\Collection when(mixed $condition, callable $callback, callable|null $default = null) Ejecuta callback si la condición es true.
     * @method static \Illuminate\Support\Collection whenEmpty(callable $callback) Ejecuta callback si la colección está vacía.
     * @method static \Illuminate\Support\Collection whenNotEmpty(callable $callback) Ejecuta callback si la colección no está vacía.
     * @method static \Illuminate\Support\Collection where(mixed $key, mixed $operator = null, mixed $value = null) Filtra elementos por una condición.
     * @method static \Illuminate\Support\Collection whereStrict(mixed $key, mixed $value) Filtra con comparación estricta (===).
     * @method static \Illuminate\Support\Collection whereBetween(string $key, array $values) Filtra elementos donde el valor está entre dos números.
     * @method static \Illuminate\Support\Collection whereIn(string $key, mixed $values, bool $strict = false) Filtra elementos donde el valor está en el array dado.
     * @method static \Illuminate\Support\Collection whereInstanceOf(string $type) Filtra elementos que son instancia de un tipo específico.
     * @method static \Illuminate\Support\Collection whereNotBetween(string $key, array $values) Filtra elementos donde el valor NO está entre dos números.
     * @method static \Illuminate\Support\Collection whereNotIn(string $key, mixed $values, bool $strict = false) Filtra elementos donde el valor NO está en el array.
     * @method static \Illuminate\Support\Collection whereNotNull(string|null $key = null) Filtra elementos donde el valor no es null.
     * @method static \Illuminate\Support\Collection whereNull(string|null $key = null) Filtra elementos donde el valor es null.
     * @method static \Illuminate\Support\Collection wrap(mixed $value) Envuelve un valor en una Collection si no lo es.
     * @method static \Illuminate\Support\Collection zip(mixed ...$items) Combina elementos de varias colecciones en grupos.
     */
    class Collection {}

    /**
     * @mixin \Illuminate\Support\Stringable
     *
     * @method static \Illuminate\Support\Stringable after(mixed $search) Retorna el resto del string después de la primera aparición de $search.
     * @method static \Illuminate\Support\Stringable afterLast(mixed $search) Retorna el resto después de la última aparición.
     * @method static \Illuminate\Support\Stringable append(mixed ...$values) Agrega uno o más strings al final.
     * @method static \Illuminate\Support\Stringable before(mixed $search) Retorna todo antes de la primera aparición de $search.
     * @method static \Illuminate\Support\Stringable beforeLast(mixed $search) Retorna todo antes de la última aparición.
     * @method static \Illuminate\Support\Stringable between(mixed $from, mixed $to) Retorna el string entre dos valores.
     * @method static \Illuminate\Support\Stringable camel() Convierte a camelCase.
     * @method static \Illuminate\Support\Stringable charAt(int $index) Retorna el carácter en una posición específica.
     * @method static \Illuminate\Support\Stringable classBasename() Retorna el nombre base de una clase (sin namespace).
     * @method static \Illuminate\Support\Stringable contains(mixed $needles) Verifica si el string contiene una o más subcadenas.
     * @method static \Illuminate\Support\Stringable containsAll(array $needles) Verifica si contiene TODAS las subcadenas.
     * @method static \Illuminate\Support\Stringable doesntContain(mixed $needles) Verifica si NO contiene la subcadena.
     * @method static \Illuminate\Support\Stringable endsWith(mixed $needles) Verifica si termina con la subcadena.
     * @method static \Illuminate\Support\Stringable exactly(mixed $value) Verifica si es exactamente igual (incluyendo tipo).
     * @method static \Illuminate\Support\Stringable explode(string $delimiter, int $limit = PHP_INT_MAX) Divide el string en array por un delimitador.
     * @method static \Illuminate\Support\Stringable finish(mixed $cap) Asegura que el string termine con un valor específico.
     * @method static \Illuminate\Support\Stringable headline() Convierte a formato título (Title Case).
     * @method static \Illuminate\Support\Stringable implode(string $glue, string|null $valueGlue = null) Une los elementos con un glue (útil para colecciones de strings).
     * @method static \Illuminate\Support\Stringable is(mixed $pattern) Verifica si coincide con un patrón (soporta * como comodín).
     * @method static \Illuminate\Support\Stringable isEmpty() Verifica si el string está vacío.
     * @method static \Illuminate\Support\Stringable isNotEmpty() Verifica si el string no está vacío.
     * @method static \Illuminate\Support\Stringable kebab() Convierte a kebab-case.
     * @method static \Illuminate\Support\Stringable length(bool $encoding = false) Retorna la longitud del string.
     * @method static \Illuminate\Support\Stringable limit(int $limit = 100, string $end = '...') Trunca el string a un límite de caracteres.
     * @method static \Illuminate\Support\Stringable lower() Convierte a minúsculas.
     * @method static \Illuminate\Support\Stringable ltrim(string|null $characters = null) Elimina espacios o caracteres al inicio.
     * @method static \Illuminate\Support\Stringable lcfirst() Convierte el primer carácter a minúscula.
     * @method static \Illuminate\Support\Stringable match(string $pattern) Obtiene la primera coincidencia de una expresión regular.
     * @method static \Illuminate\Support\Stringable matchAll(string $pattern) Obtiene todas las coincidencias de una regex.
     * @method static \Illuminate\Support\Stringable newLine(int $count = 1) Agrega una o más líneas nuevas (\\n).
     * @method static \Illuminate\Support\Stringable padBoth(int $length, string $pad = ' ') Rellena ambos lados a una longitud fija.
     * @method static \Illuminate\Support\Stringable padLeft(int $length, string $pad = ' ') Rellena a la izquierda.
     * @method static \Illuminate\Support\Stringable padRight(int $length, string $pad = ' ') Rellena a la derecha.
     * @method static \Illuminate\Support\Stringable pipe(callable $callback) Pasa el string a un callback y retorna el resultado.
     * @method static \Illuminate\Support\Stringable plural(int $count = 2) Convierte a plural.
     * @method static \Illuminate\Support\Stringable prepend(mixed ...$values) Agrega uno o más strings al inicio.
     * @method static \Illuminate\Support\Stringable remove(mixed $search, string|null $side = null) Elimina todas las ocurrencias de $search.
     * @method static \Illuminate\Support\Stringable repeat(int $times) Repite el string N veces.
     * @method static \Illuminate\Support\Stringable replace(mixed $search, mixed $replace, bool $caseSensitive = true) Reemplaza ocurrencias de $search por $replace.
     * @method static \Illuminate\Support\Stringable replaceArray(mixed $search, array $replace) Reemplaza usando un array de reemplazos secuencialmente.
     * @method static \Illuminate\Support\Stringable replaceFirst(mixed $search, mixed $replace) Reemplaza la primera ocurrencia.
     * @method static \Illuminate\Support\Stringable replaceLast(mixed $search, mixed $replace) Reemplaza la última ocurrencia.
     * @method static \Illuminate\Support\Stringable rtrim(string|null $characters = null) Elimina espacios al final.
     * @method static \Illuminate\Support\Stringable singular() Convierte a singular.
     * @method static \Illuminate\Support\Stringable slug(string $separator = '-', string|null $language = null, string|null $dictionary = 'en') Convierte a slug URL.
     * @method static \Illuminate\Support\Stringable snake() Convierte a snake_case.
     * @method static \Illuminate\Support\Stringable split(string $pattern) Divide el string usando una regex.
     * @method static \Illuminate\Support\Stringable squish() Elimina espacios extra (múltiples espacios, tabs, saltos de línea).
     * @method static \Illuminate\Support\Stringable start(mixed $prefix) Asegura que el string comience con un prefijo.
     * @method static \Illuminate\Support\Stringable startsWith(mixed $needles) Verifica si comienza con un valor.
     * @method static \Illuminate\Support\Stringable studly() Convierte a StudlyCase.
     * @method static \Illuminate\Support\Stringable substr(int $start, int|null $length = null) Retorna una porción del string.
     * @method static \Illuminate\Support\Stringable substrCount(string $needle, int $offset = 0, int|null $length = null) Cuenta ocurrencias de una subcadena.
     * @method static \Illuminate\Support\Stringable swap(array $map) Intercambia claves por valores en el string.
     * @method static \Illuminate\Support\Stringable tap(callable $callback) Pasa el Stringable a un callback sin modificarlo.
     * @method static \Illuminate\Support\Stringable test(string $pattern) Prueba si el string coincide con una regex.
     * @method static \Illuminate\Support\Stringable title() Convierte a Title Case.
     * @method static \Illuminate\Support\Stringable trim(string|null $characters = null) Elimina espacios al inicio y final.
     * @method static \Illuminate\Support\Stringable ucfirst() Convierte el primer carácter a mayúscula.
     * @method static \Illuminate\Support\Stringable ucsplit() Divide el string en mayúsculas (para partir camelCase).
     * @method static \Illuminate\Support\Stringable unwrap(string $before, string|null $after = null) Elimina caracteres de apertura y cierre.
     * @method static \Illuminate\Support\Stringable upper() Convierte a mayúsculas.
     * @method static \Illuminate\Support\Stringable value() Retorna el string como valor nativo (cuando quieres el string plano fuera de la cadena fluente).
     * @method static \Illuminate\Support\Stringable when(mixed $condition, callable $callback, callable|null $default = null) Ejecuta callback si la condición es true.
     * @method static \Illuminate\Support\Stringable whenContains(mixed $needles, callable $callback, callable|null $default = null) Ejecuta callback si contiene la subcadena.
     * @method static \Illuminate\Support\Stringable whenEmpty(callable $callback, callable|null $default = null) Ejecuta callback si está vacío.
     * @method static \Illuminate\Support\Stringable whenNotEmpty(callable $callback, callable|null $default = null) Ejecuta si no está vacío.
     * @method static \Illuminate\Support\Stringable whenStartsWith(mixed $prefix, callable $callback, callable|null $default = null) Ejecuta si comienza con.
     * @method static \Illuminate\Support\Stringable whenEndsWith(mixed $suffix, callable $callback, callable|null $default = null) Ejecuta si termina con.
     * @method static \Illuminate\Support\Stringable whenExactly(mixed $value, callable $callback, callable|null $default = null) Ejecuta si exactamente igual.
     * @method static \Illuminate\Support\Stringable whenNotExactly(mixed $value, callable $callback, callable|null $default = null) Ejecuta si no es exactamente igual.
     * @method static \Illuminate\Support\Stringable whenIs(mixed $pattern, callable $callback, callable|null $default = null) Ejecuta si coincide con patrón.
     * @method static \Illuminate\Support\Stringable whenIsAscii(callable $callback, callable|null $default = null) Ejecuta si es ASCII.
     * @method static \Illuminate\Support\Stringable whenNotEmpty(callable $callback, callable|null $default = null) Ejecuta si no está vacío.
     * @method static \Illuminate\Support\Stringable whenUuid(callable $callback, callable|null $default = null) Ejecuta si es un UUID válido.
     * @method static \Illuminate\Support\Stringable words(int $words = 100, string $end = '...') Trunca a un número de palabras.
     */
    class Stringable {}
}

namespace Illuminate\Http\Client {

    /**
     * @mixin \Illuminate\Http\Client\Factory
     *
     * @method static \Illuminate\Http\Client\PendingRequest baseUrl(string $url) Establece la URL base para todas las solicitudes.
     * @method static \Illuminate\Http\Client\PendingRequest withHeaders(array $headers) Agrega headers HTTP a la solicitud.
     * @method static \Illuminate\Http\Client\PendingRequest withToken(string $token, string $type = 'Bearer') Agrega un token de autenticación Bearer.
     * @method static \Illuminate\Http\Client\PendingRequest withBasicAuth(string $username, string $password) Agrega autenticación básica HTTP.
     * @method static \Illuminate\Http\Client\PendingRequest timeout(int $seconds) Establece el tiempo máximo de espera de la solicitud.
     * @method static \Illuminate\Http\Client\PendingRequest retry(int $times, int $sleepMilliseconds = 0, ?callable $when = null, bool $throw = true) Configura reintentos automáticos en caso de fallo.
     * @method static \Illuminate\Http\Client\Response get(string $url, array|string|null $query = null) Envía una solicitud GET.
     * @method static \Illuminate\Http\Client\Response post(string $url, array $data = []) Envía una solicitud POST con datos.
     * @method static \Illuminate\Http\Client\Response put(string $url, array $data = []) Envía una solicitud PUT.
     * @method static \Illuminate\Http\Client\Response patch(string $url, array $data = []) Envía una solicitud PATCH.
     * @method static \Illuminate\Http\Client\Response delete(string $url, array $data = []) Envía una solicitud DELETE.
     * @method static \Illuminate\Http\Client\Response head(string $url, array|string|null $query = null) Envía una solicitud HEAD.
     * @method static \Illuminate\Http\Client\Response send(string $method, string $url, array $options = []) Envía una solicitud HTTP con método personalizado.
     * @method static \Illuminate\Http\Client\PendingRequest withOptions(array $options) Configura opciones adicionales de Guzzle (verify, debug, proxy, etc.).
     * @method static \Illuminate\Http\Client\PendingRequest withCookies(array $cookies, string $domain) Envía cookies con la solicitud.
     * @method static \Illuminate\Http\Client\PendingRequest bodyFormat(string $format) Cambia el formato del body (json, form_params, multipart).
     * @method static \Illuminate\Http\Client\PendingRequest contentType(string $contentType) Fuerza un Content-Type específico.
     * @method static \Illuminate\Http\Client\PendingRequest accept(string $contentType) Establece el header Accept.
     * @method static \Illuminate\Http\Client\PendingRequest acceptJson() Establece Accept: application/json.
     * @method static \Illuminate\Http\Client\PendingRequest asJson() Envía el body como JSON.
     * @method static \Illuminate\Http\Client\PendingRequest asForm() Envía el body como formulario URL-encoded.
     * @method static \Illuminate\Http\Client\PendingRequest asMultipart() Envía el body como multipart (archivos).
     * @method static \Illuminate\Http\Client\PendingRequest attach(string $name, string $contents, string|null $filename = null) Adjunta un archivo a la solicitud.
     * @method static \Illuminate\Http\Client\PendingRequest beforeSending(callable $callback) Callback ejecutado justo antes de enviar.
     * @method static \Illuminate\Http\Client\PendingRequest withoutRedirecting() Deshabilita las redirecciones automáticas.
     * @method static \Illuminate\Http\Client\PendingRequest withoutVerifying() Deshabilita la verificación SSL (solo desarrollo).
     * @method static \Illuminate\Http\Client\PendingRequest dontThrow() Previene que respuestas con error lancen excepciones.
     * @method static \Illuminate\Http\Client\PendingRequest throw() Habilita que respuestas con error lancen RequestException.
     * @method static \Illuminate\Http\Client\PendingRequest connectTimeout(int $seconds) Tiempo máximo de espera para establecer conexión.
     * @method static \Illuminate\Http\Client\PendingRequest withUserAgent(string $userAgent) Establece el User-Agent.
     * @method static \Illuminate\Http\Client\PendingRequest stub(callable $callback) Registra un stub para la solicitud (útil en tests).
     * @method static \Illuminate\Http\Client\Factory fake(array|callable $callback = []) Reemplaza el cliente HTTP real por uno falso (tests).
     * @method static \Illuminate\Http\Client\Factory sequence(array $responses = []) Define una secuencia de respuestas falsas (tests).
     * @method static \Illuminate\Http\Client\Factory assertSent(callable $callback) Afirma que se envió una solicitud (tests).
     * @method static \Illuminate\Http\Client\Factory assertNotSent(callable $callback) Afirma que NO se envió una solicitud (tests).
     * @method static \Illuminate\Http\Client\Factory assertNothingSent() Afirma que no se envió ninguna solicitud (tests).
     * @method static \Illuminate\Http\Client\Factory assertSentCount(int $count) Afirma el número de solicitudes enviadas (tests).
     * @method static \Illuminate\Http\Client\Factory recorded(callable|null $callback = null) Obtiene las solicitudes registradas (tests).
     * @method static \Illuminate\Http\Client\ResponseSequence sequence(array $responses = []) Define una secuencia de respuestas.
     * @method static \Illuminate\Http\Client\Pool pool(callable $callback) Envía solicitudes en pool (concurrentes).
     */
    class Http {}
}
