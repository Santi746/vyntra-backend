# Prompt IA — Test WSS E2E: HTTP → Controller → DB → Redis → Reverb → Cliente

> Prompt ejecutable para IA. Instrucciones paso a paso, sin párrafos, sin secciones, sin explicaciones.
> La IA ejecuta cada paso en orden. Si un paso falla, se DETIENE y reporta.

---

PASO 1: `docker ps --format "{{.Names}}" | Select-String "postgres|redis"; netstat -ano | Select-String ":6379|:8080|:80" | Select-String "LISTENING"`

→ DEBES ver: 1 línea con `vyntra-postgres` (Docker), 1 línea `redis` (Docker), más 2 LISTENING (8080 Reverb, 80 Herd/Nginx). PostgreSQL ya no corre directo en Windows.
→ SI FALLA: DETENERSE. Reportar qué servicio falta. Ejecutar: `docker compose up -d` para postgres/pgbouncer/octane, `docker start redis`, `php artisan reverb:start`, `herd serve`.

---

PASO 2: `$login = Invoke-WebRequest -Uri http://vyntra-backend.test/api/auth/login -Method POST -ContentType application/json -Body '{"email":"test@example.com","password":"password"}' -UseBasicParsing; $token = ($login.Content | ConvertFrom-Json).data.token; Write-Host "TOKEN_OK:$($token.Substring(0,20))..."`

→ DEBES ver: `TOKEN_OK:` seguido de los primeros 20 caracteres del token
→ SI FALLA: DETENERSE. Reportar status HTTP y body de error. Verificar que Herd está corriendo y vyntra-backend.test resuelve.

---

PASO 3: `$token = ((Invoke-WebRequest -Uri http://vyntra-backend.test/api/auth/login -Method POST -ContentType application/json -Body '{"email":"test@example.com","password":"password"}' -UseBasicParsing).Content | ConvertFrom-Json).data.token; $authBody = '{"socket_id":"123456.789","channel_name":"private-channel.019e7af3-bd77-734d-9c30-4681ad047b51"}'; $auth = Invoke-WebRequest -Uri http://vyntra-backend.test/broadcasting/auth -Method POST -Headers @{Authorization="Bearer $token"} -ContentType application/json -Body $authBody -UseBasicParsing; Write-Host "AUTH_OK:$($auth.Content)"`

→ DEBES ver: `AUTH_OK:{"auth":"...` (JSON con campo auth, NO 403)
→ SI FALLA: DETENERSE. Si 403: revisar `bootstrap/app.php` -> `->withBroadcasting()` debe tener `middleware: ['auth:sanctum']`. Si 404: Reverb no está corriendo o .env mal configurado.

---

PASO 4: Crear archivo `tests/manual/wss_e2e.cjs` con este contenido EXACTO:

```javascript
// tests/manual/wss_e2e.cjs
// Tests WSS E2E — 5 tests secuenciales. NO MODIFICAR.
// node tests/manual/wss_e2e.cjs

const { Pusher } = require('pusher-js');
const http = require('http');
const crypto = require('crypto');

// ─── CONFIG ────────────────────────────────────────────────
const CFG = {
    REVERB_HOST: '127.0.0.1',
    REVERB_PORT: 8080,
    REVERB_KEY: '3559175319fbab34ca59e2ac05bbc6a7',
    API_HOST: 'vyntra-backend.test',
    API_PORT: 80,
    EMAIL: 'test@example.com',
    PASSWORD: 'password',
    USER_UUID: '019e7af3-ba31-718e-9bad-399333cd81d8',
    OTHER_USER_UUID: '019e7af3-baa0-70fb-9c05-43b42f25bea4',
    CLUB_UUID: '019e7af3-bcc8-70d0-bf84-5d7b3f9005cf',
    CHANNEL_UUID: '019e7af3-bd77-734d-9c30-4681ad047b51',
    DM_UUID: '019e7af3-bc16-7178-9717-bfcea4a4cba8',
};

const results = [];

// ─── HELPERS ───────────────────────────────────────────────
function uuidv4() { return crypto.randomUUID(); }

function log(tag, ...args) {
    const ts = new Date().toISOString().substring(11, 23);
    console.log(`[${ts}] ${tag}`, ...args);
}

function header(t) {
    console.log('\n' + '='.repeat(72));
    console.log('  ' + t);
    console.log('='.repeat(72));
}

function httpRequest(method, path, token, body) {
    return new Promise((resolve, reject) => {
        const data = body ? JSON.stringify(body) : null;
        const opts = {
            host: CFG.API_HOST,
            port: CFG.API_PORT,
            path: path,
            method: method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
            },
        };
        if (token) opts.headers['Authorization'] = 'Bearer ' + token;
        if (data) opts.headers['Content-Length'] = Buffer.byteLength(data);

        const req = http.request(opts, (res) => {
            let chunks = '';
            res.on('data', (c) => { chunks += c; });
            res.on('end', () => {
                let parsed = null;
                try { parsed = JSON.parse(chunks); } catch (e) { parsed = chunks; }
                resolve({ status: res.statusCode, body: parsed });
            });
        });
        req.on('error', (e) => reject(new Error(method + ' ' + path + ' — ' + e.message)));
        if (data) req.write(data);
        req.end();
    });
}

async function login() {
    const res = await httpRequest('POST', '/api/auth/login', null, {
        email: CFG.EMAIL,
        password: CFG.PASSWORD,
    });
    if (res.status !== 200) {
        throw new Error('LOGIN_FALLIDO — HTTP ' + res.status + ': ' + JSON.stringify(res.body).substring(0, 200));
    }
    const token = res.body?.data?.token;
    if (!token) {
        throw new Error('LOGIN_FALLIDO — No se encontro data.token en respuesta');
    }
    return token;
}

function createPusher(token) {
    Pusher.log = () => {};
    return new Pusher(CFG.REVERB_KEY, {
        wsHost: CFG.REVERB_HOST,
        wsPort: CFG.REVERB_PORT,
        forceTLS: false,
        encrypted: false,
        enabledTransports: ['ws'],
        disabledTransports: ['wss'],
        authEndpoint: 'http://' + CFG.API_HOST + '/broadcasting/auth',
        auth: {
            headers: {
                Authorization: 'Bearer ' + token,
                Accept: 'application/json',
            },
        },
        cluster: 'mt1',
    });
}

function subscribe(pusher, channelName, timeoutMs) {
    if (!timeoutMs) timeoutMs = 10000;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            reject(new Error('SUBSCRIBE_TIMEOUT — ' + channelName + ' (>' + timeoutMs + 'ms)'));
        }, timeoutMs);
        const ch = pusher.subscribe(channelName);
        ch.bind('pusher:subscription_succeeded', () => {
            clearTimeout(timer);
            resolve(ch);
        });
        ch.bind('pusher:subscription_error', (err) => {
            clearTimeout(timer);
            reject(new Error('SUBSCRIBE_ERROR — ' + channelName + ': ' + JSON.stringify(err)));
        });
    });
}

function waitForEvent(channel, eventName, timeoutMs) {
    if (!timeoutMs) timeoutMs = 8000;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => {
            reject(new Error('TIMEOUT_ESPERANDO_EVENTO — ' + eventName + ' (>' + timeoutMs + 'ms)'));
        }, timeoutMs);
        channel.bind(eventName, (data) => {
            clearTimeout(timer);
            resolve(data);
        });
    });
}

function safeGet(data, pathStr) {
    const parts = pathStr.split('.');
    let current = data;
    for (const p of parts) {
        if (current === null || current === undefined || typeof current !== 'object') return undefined;
        current = current[p];
    }
    return current;
}

async function runTest(name, fn) {
    log('TEST', '▶ ' + name);
    try {
        const detail = await fn();
        results.push({ name: name, pass: true, detail: detail });
        log('TEST', '  ✅ PASS — ' + detail);
    } catch (e) {
        results.push({ name: name, pass: false, detail: e.message });
        log('TEST', '  ❌ FAIL — ' + e.message);
    }
}

// ─── MAIN ──────────────────────────────────────────────────
(async () => {
    header('TEST WSS E2E — 5 TESTS SECUENCIALES');

    // --- LOGIN ---
    log('AUTH', 'Obteniendo token...');
    const token = await login();
    log('AUTH', 'Token: ' + token.substring(0, 20) + '...');

    // --- CONECTAR WSS ---
    log('WS', 'Conectando a Reverb en ws://' + CFG.REVERB_HOST + ':' + CFG.REVERB_PORT + ' ...');
    const pusher = createPusher(token);
    await new Promise((resolve, reject) => {
        const t = setTimeout(() => reject(new Error('WS_CONNECTION_TIMEOUT')), 10000);
        pusher.connection.bind('connected', () => {
            clearTimeout(t);
            log('WS', 'Conectado');
            resolve();
        });
        pusher.connection.bind('error', (e) => {
            clearTimeout(t);
            reject(new Error('WS_CONNECTION_ERROR: ' + JSON.stringify(e)));
        });
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 1: MessageCreated
    // ═══════════════════════════════════════════════════════════
    await runTest('1. MessageCreated', async () => {
        const chName = 'private-channel.' + CFG.CHANNEL_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'MessageCreated');

        const clientUuid = uuidv4();
        const httpRes = await httpRequest(
            'POST',
            '/api/channels/' + CFG.CHANNEL_UUID + '/messages',
            token,
            { client_uuid: clientUuid, content: 'WSS test message 1' },
        );
        if (httpRes.status !== 201) {
            throw new Error('HTTP ' + httpRes.status + ' (esperado 201): ' + JSON.stringify(httpRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const receivedContent = safeGet(payload, 'content') || '?';
        const receivedClientUuid = safeGet(payload, 'client_uuid') || '?';

        if (receivedContent !== 'WSS test message 1') {
            throw new Error('CONTENT_MISMATCH — esperado "WSS test message 1", recibido "' + receivedContent + '"');
        }
        if (receivedClientUuid !== clientUuid) {
            throw new Error('CLIENT_UUID_MISMATCH — esperado ' + clientUuid + ', recibido ' + receivedClientUuid);
        }

        return 'content="WSS test message 1" client_uuid=' + receivedClientUuid.substring(0, 8) + '...';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 2: DmMessageCreated
    // ═══════════════════════════════════════════════════════════
    await runTest('2. DmMessageCreated', async () => {
        const chName = 'private-dm-conversation.' + CFG.DM_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'DmMessageCreated');

        const clientUuid = uuidv4();
        const httpRes = await httpRequest(
            'POST',
            '/api/dm-conversations/' + CFG.DM_UUID + '/messages',
            token,
            { client_uuid: clientUuid, content: 'WSS test DM 2' },
        );
        if (httpRes.status !== 201) {
            throw new Error('HTTP ' + httpRes.status + ' (esperado 201): ' + JSON.stringify(httpRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const receivedContent = safeGet(payload, 'content') || '?';
        const receivedClientUuid = safeGet(payload, 'client_uuid') || '?';

        if (receivedContent !== 'WSS test DM 2') {
            throw new Error('CONTENT_MISMATCH — esperado "WSS test DM 2", recibido "' + receivedContent + '"');
        }
        if (receivedClientUuid !== clientUuid) {
            throw new Error('CLIENT_UUID_MISMATCH');
        }

        return 'content="WSS test DM 2" client_uuid=' + receivedClientUuid.substring(0, 8) + '...';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 3: ClubUpdated
    // ═══════════════════════════════════════════════════════════
    await runTest('3. ClubUpdated', async () => {
        const chName = 'private-club.' + CFG.CLUB_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'ClubUpdated');

        const newName = 'WSS Test Club ' + Date.now();
        const httpRes = await httpRequest(
            'PATCH',
            '/api/clubs/' + CFG.CLUB_UUID,
            token,
            { name: newName },
        );

        if (httpRes.status === 403) {
            return 'SKIP — tester no tiene permiso MANAGE_CLUB en el club (HTTP 403)';
        }
        if (httpRes.status !== 200) {
            throw new Error('HTTP ' + httpRes.status + ' (esperado 200): ' + JSON.stringify(httpRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const receivedName = safeGet(payload, 'name') || '?';

        if (receivedName !== newName) {
            throw new Error('NAME_MISMATCH — esperado "' + newName + '", recibido "' + receivedName + '"');
        }

        return 'name="' + receivedName + '"';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 4: NotificationCreated
    // ═══════════════════════════════════════════════════════════
    await runTest('4. NotificationCreated', async () => {
        const chName = 'private-user.' + CFG.OTHER_USER_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'NotificationCreated');

        const httpRes = await httpRequest(
            'POST',
            '/api/user/friend-requests',
            token,
            { receiver_uuid: CFG.OTHER_USER_UUID },
        );

        if (httpRes.status === 409) {
            return 'SKIP — friend request ya existe (HTTP 409), el evento no se dispara';
        }
        if (httpRes.status !== 201 && httpRes.status !== 200) {
            throw new Error('HTTP ' + httpRes.status + ' (esperado 201/200): ' + JSON.stringify(httpRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const type = safeGet(payload, 'type') || '?';

        return 'type=' + type;
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 5: CategoryDeleted
    // ═══════════════════════════════════════════════════════════
    await runTest('5. CategoryDeleted', async () => {
        const chName = 'private-club.' + CFG.CLUB_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        // Crear categoria temporal para borrar
        const clientUuid = uuidv4();
        const createRes = await httpRequest(
            'POST',
            '/api/clubs/' + CFG.CLUB_UUID + '/categories',
            token,
            {
                client_uuid: clientUuid,
                name: 'WSS Test ' + Date.now(),
                sort_order: 999,
                is_private: false,
            },
        );

        if (createRes.status === 403) {
            return 'SKIP — tester no tiene permiso MANAGE_CHANNELS en el club (HTTP 403 al crear)';
        }
        if (createRes.status !== 201 && createRes.status !== 200) {
            throw new Error('HTTP_CREATE_CATEGORY ' + createRes.status + ': ' + JSON.stringify(createRes.body).substring(0, 200));
        }

        const categoryUuid = safeGet(createRes.body, 'data.uuid');
        if (!categoryUuid) {
            throw new Error('No se pudo extraer uuid de la categoria creada');
        }
        log('CAT', 'Categoria creada: ' + categoryUuid);

        const eventPromise = waitForEvent(ch, 'CategoryDeleted');

        const deleteRes = await httpRequest(
            'DELETE',
            '/api/clubs/' + CFG.CLUB_UUID + '/categories/' + categoryUuid,
            token,
            null,
        );
        if (deleteRes.status !== 204) {
            throw new Error('HTTP ' + deleteRes.status + ' (esperado 204): ' + JSON.stringify(deleteRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const payloadUuid = payload.uuid || '?';
        const action = payload.action || '?';

        if (action !== 'delete') {
            throw new Error('ACTION_MISMATCH — esperado "delete", recibido "' + action + '"');
        }
        if (payloadUuid !== categoryUuid) {
            throw new Error('UUID_MISMATCH — esperado ' + categoryUuid + ', recibido ' + payloadUuid);
        }

        return 'uuid=' + payloadUuid.substring(0, 8) + '... action=' + action;
    });

    // ─── DESCONECTAR ──────────────────────────────────────────
    pusher.disconnect();
    log('WS', 'Desconectado');

    // ─── REPORTE FINAL ────────────────────────────────────────
    header('RESULTADOS');

    let passed = 0;
    let skipped = 0;
    let failed = 0;

    for (const r of results) {
        let status;
        if (r.pass && r.detail && r.detail.startsWith('SKIP')) {
            status = 'SKIP';
            skipped++;
        } else if (r.pass) {
            status = 'PASS';
            passed++;
        } else {
            status = 'FAIL';
            failed++;
        }
        console.log('  ' + status.padEnd(6) + '  ' + r.name.padEnd(32) + '  ' + (r.detail || '').substring(0, 60));
    }

    console.log('='.repeat(72));
    console.log('  PASS: ' + passed + '  |  SKIP: ' + skipped + '  |  FAIL: ' + failed + '  |  TOTAL: ' + results.length);
    console.log('='.repeat(72));

    if (failed > 0) {
        console.log('\n  ❌ HAY TESTS FALLIDOS — Revisar logs del worker y del script');
        process.exit(1);
    } else {
        console.log('\n  ✅ TODOS LOS TESTS COMPLETADOS');
        process.exit(0);
    }

})().catch((e) => {
    console.error('\n  ❌ FATAL: ' + e.message);
    process.exit(1);
});
```

→ DEBES ver: el archivo se crea sin errores
→ SI FALLA: DETENERSE. Reportar error de escritura (permisos, disco lleno, ruta inexistente). Verificar que `tests/manual/` existe.

---

PASO 5: `node tests/manual/wss_e2e.cjs`

→ DEBES ver: la salida del script ejecutando los 5 tests secuencialmente. Al final debe mostrar:

```
  =========================================================================
    RESULTADOS
  =========================================================================
    PASS    1. MessageCreated                content="WSS test message 1"...
    PASS    2. DmMessageCreated              content="WSS test DM 2"...
    PASS    3. ClubUpdated                   name="WSS Test Club ..."
    PASS    4. NotificationCreated           type=...
    PASS    5. CategoryDeleted               uuid=... action=delete
  =========================================================================
    PASS: 5  |  SKIP: 0  |  FAIL: 0  |  TOTAL: 5
  =========================================================================
```

Los tests 3 y 5 pueden salir como SKIP si tester_vyntra no tiene permisos de MANAGE_CLUB o MANAGE_CHANNELS respectivamente. El test 4 puede salir SKIP si el friend request ya existe (HTTP 409).

→ SI FALLA: DETENERSE. Reportar el mensaje de error del test exacto que falló. NO reintentar. NO continuar.

---

PASO 6: `$stats = @{}; Get-Content .\tests\manual\wss_e2e.cjs -Raw | Select-String -Pattern "PASS|FAIL|SKIP" -AllMatches | ForEach-Object { $stats[$_.Value]++ }; $stats`

→ DEBES ver: un resumen de conteo `PASS`, `FAIL`, `SKIP` de la salida del script
→ SI FALLA: DETENERSE. El script no se ejecutó o no generó salida.

---

PASO 7: `Write-Host "RESUMEN_FINAL: $((Get-Content .\tests\manual\wss_e2e.cjs -Raw | Select-String -Pattern "FAIL" -AllMatches).Matches.Count) fallos encontrados"`

→ DEBES ver: `RESUMEN_FINAL: 0 fallos encontrados`
→ SI FALLA: Reportar número de fallos. Si > 0, la IA debe leer el output del script para identificar cuáles tests fallaron y reportar el detalle exacto.

---

REGLAS PARA LA IA QUE EJECUTA ESTE PROMPT:

- Ejecutar los pasos en orden SECUENCIAL. No saltar pasos.
- Si un paso falla: DETENERSE (no continuar con el siguiente), reportar el error EXACTO con el mensaje de error completo.
- Si el script (PASO 5) muestra FAIL en cualquier test, la IA debe leer la línea de error específica y reportarla textualmente.
- Los SKIP son aceptables y esperados (dependen de permisos del usuario de prueba). NO detenerse por SKIP.
- NO modificar el script. NO parchear permisos. NO crear datos de prueba adicionales. Reportar y detenerse.
- Al final, la IA debe declarar: "TEST WSS E2E COMPLETADO — X PASS, Y SKIP, Z FAIL"
