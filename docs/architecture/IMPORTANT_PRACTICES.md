# Vyntra System Architecture - Phase: HTTP Lifecycle & Async Prep

> **Frameworks:** Laravel 13 (Backend) / Next.js (Frontend)  
> **Stack de Infraestructura Inminente:** Redis, Laravel Reverb, Octane, Horizon.

## 📌 Contexto Actual del Sistema (System Prompt para IA)

El proyecto se encuentra en la fase de **Cierre del Ciclo HTTP**. Las capas de Modelos, Base de Datos, Middlewares, FormRequests, Policies y Controllers están definidas o en proceso de cierre.

---

## 🏗️ Reglas de Implementación Inmediata (Aplicar AHORA)

### REGLA 0: No Adelantar Infraestructura No HTTP

**Descripción:** Hasta que las Rutas y los API Resources del ciclo HTTP actual estén 100% testeados y cerrados, no se debe configurar WebSockets, Octane ni Horizon.

**Prohibición:** BAJO NINGUNA CIRCUNSTANCIA debes adelantar la configuración de WebSockets (Reverb), Servidores en Memoria (Octane) o Colas (Horizon).

**Acción:** Preparar el código HTTP para que sea compatible con esa infraestructura futura sin implementarla todavía.

### REGLA 1: Serialización Segura de Identificadores (Capa: API Resources)

**Descripción:** JavaScript (Next.js) tiene un límite `MAX_SAFE_INTEGER`. Para evitar pérdida de precisión y unificar la tipología del Gateway, la API nunca debe devolver identificadores numéricos puros o UUIDs crudos sin castear.

**Acción:** En TODOS los `ApiResource` (ej. `ClubResource`, `ChannelMessageResource`), debes forzar el casteo a String de cualquier ID, UUID (generado por el sistema) o llave foránea.

**Ejemplo Correcto:**

```php
return [
    'id' => (string) $this->uuid,
    'club_id' => (string) $this->club_uuid,
    // ...
];
```

### REGLA 2: Limitación de Tráfico Estricta (Capa: Rutas / Middleware)

**Descripción:** Para proteger la futura infraestructura de Redis/Reverb del spam o abusos, los endpoints de escritura masiva deben tener Rate Limiting nativo de Laravel configurado en el enrutador HTTP.

**Acción:** Utilizar el middleware throttle explícitamente en `routes/api.php` para rutas POST/PUT/DELETE de alta frecuencia (ej. envío de mensajes, creación de canales).

**Ejemplo Correcto:**

```php
Route::middleware(['auth:sanctum', 'throttle:10,1'])->group(function () {
    Route::post('/channels/{channel}/messages', [MessageController::class, 'store']);
});
```

### REGLA 3: Desacoplamiento de Controladores (Capa: Controllers)

**Descripción:** Con la inminente llegada de Octane y Horizon, el controlador HTTP no debe ejecutar ninguna tarea bloqueante de larga duración (ej. envío de emails, cálculos pesados, o broadcasts).

**Acción:** El Controller se limita estrictamente a:

1. Validar (vía FormRequest).
2. Autorizar (vía Policy).
3. Ejecutar la operación de Base de Datos (Write).
4. Despachar un Evento o Job (que posteriormente manejará Horizon/Reverb).
5. Retornar el API Resource (Read).

**Restricción:** No utilices `broadcast()` directamente dentro del método del controlador de forma síncrona. Despacha un evento que implemente `ShouldBroadcast` o `ShouldQueue`.

### REGLA 4: Contrato de Payload para WebSockets (Preparación de Eventos)

**Descripción:** Para emular la arquitectura limpia de plataformas de alta concurrencia, los datos que viajen en tiempo real deben tener una estructura predecible (Opcode/Event structure).

**Acción:** Cuando se creen los Eventos (ej. `MessageCreatedEvent`) que se enviarán por Reverb, el método `broadcastWith()` debe retornar un JSON estandarizado, separando el tipo de evento de la carga de datos.

**Ejemplo Correcto:**

```php
public function broadcastWith(): array
{
    return [
        't' => 'MESSAGE_CREATE', // Identificador del evento para el Frontend
        'd' => (new MessageResource($this->message))->resolve(), // Data pura
    ];
}
```

---

## 🛑 Operaciones Restringidas (NO HACER TODAVÍA)

Si se te pide trabajar en el código actual, OMITE lo siguiente hasta que se te indique explícitamente pasar a la fase de Infraestructura:

- No modifiques las migraciones para forzar ULIDs/Snowflakes. Mantendremos UUIDs por ahora.
- No implementes lógica de presencia "Heartbeat" (estado En Línea/Ausente).
- No configures bases de datos secundarias (Replicas de Lectura).
- No intentes instalar o configurar `laravel/octane` o `laravel/reverb` en los archivos base. Limítate al ciclo HTTP estándar.