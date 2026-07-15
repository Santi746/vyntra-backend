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
        // Si ya estamos suscritos, unsubscribe primero para evitar falsos success
        const existing = pusher.channel(channelName);
        if (existing) {
            pusher.unsubscribe(channelName);
        }
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
        let ch;
        try {
            ch = await subscribe(pusher, chName);
        } catch (e) {
            if (e.message.includes('SUBSCRIBE_ERROR') && e.message.includes('status: 403')) {
                return 'SKIP — no se puede suscribir al canal privado de otro usuario (HTTP 403)';
            }
            throw e;
        }
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

    // ═══════════════════════════════════════════════════════════
    // TEST 6: ClubCreated — cubre private-user.* + Resource payload
    // ═══════════════════════════════════════════════════════════
    let tempClubUuid = null;
    await runTest('6. ClubCreated', async () => {
        const chName = 'private-user.' + CFG.USER_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'ClubCreated');

        const clientUuid = uuidv4();
        const clubName = 'WSS Temp ' + Date.now();
        const createRes = await httpRequest(
            'POST', '/api/clubs', token,
            {
                client_uuid: clientUuid,
                name: clubName,
                category_tag: 'general',
            },
        );
        if (createRes.status !== 201) {
            throw new Error('HTTP ' + createRes.status + ' (esperado 201): ' + JSON.stringify(createRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const receivedName = safeGet(payload, 'name') || '?';

        tempClubUuid = safeGet(createRes.body, 'data.uuid') || safeGet(payload, 'uuid');
        log('CLUB', 'Club temporal creado: ' + tempClubUuid);

        if (receivedName !== clubName) {
            throw new Error('NAME_MISMATCH — esperado "' + clubName + '", recibido "' + receivedName + '"');
        }

        return 'name="' + receivedName + '" club=' + (tempClubUuid || '').substring(0, 8) + '...';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 7: RoleCreated — cubre Role Resource a private-club.*
    // ═══════════════════════════════════════════════════════════
    await runTest('7. RoleCreated', async () => {
        const chName = 'private-club.' + CFG.CLUB_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'RoleCreated');

        const clientUuid = uuidv4();
        const roleName = 'WSS Role ' + Date.now();
        const createRes = await httpRequest(
            'POST', '/api/clubs/' + CFG.CLUB_UUID + '/roles', token,
            { client_uuid: clientUuid, name: roleName, color: '#FF0000', permissions: 0 },
        );

        if (createRes.status === 403) {
            return 'SKIP — tester no tiene permiso MANAGE_ROLES en el club (HTTP 403)';
        }
        if (createRes.status !== 201 && createRes.status !== 200) {
            throw new Error('HTTP ' + createRes.status + ' (esperado 201): ' + JSON.stringify(createRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const receivedName = safeGet(payload, 'name') || '?';

        if (receivedName !== roleName) {
            throw new Error('NAME_MISMATCH — esperado "' + roleName + '", recibido "' + receivedName + '"');
        }

        return 'name="' + receivedName + '"';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 8: MessageUpdated — cubre array manual en private-channel.*
    // ═══════════════════════════════════════════════════════════
    await runTest('8. MessageUpdated', async () => {
        const chName = 'private-channel.' + CFG.CHANNEL_UUID;
        const ch = await subscribe(pusher, chName);

        // Crear mensaje temporal para luego actualizar
        const msgClientUuid = uuidv4();
        const msgCreateRes = await httpRequest(
            'POST', '/api/channels/' + CFG.CHANNEL_UUID + '/messages', token,
            { client_uuid: msgClientUuid, content: 'WSS to update ' + Date.now() },
        );
        if (msgCreateRes.status !== 201) {
            throw new Error('HTTP_CREATE_MSG ' + msgCreateRes.status + ': ' + JSON.stringify(msgCreateRes.body).substring(0, 200));
        }
        const msgUuid = safeGet(msgCreateRes.body, 'data.uuid');
        if (!msgUuid) throw new Error('No se pudo extraer uuid del mensaje creado');
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'MessageUpdated');

        const newContent = 'WSS updated ' + Date.now();
        const updateRes = await httpRequest(
            'PATCH', '/api/channels/' + CFG.CHANNEL_UUID + '/messages/' + msgUuid, token,
            { content: newContent },
        );
        if (updateRes.status === 403) {
            return 'SKIP — tester no es el sender del mensaje (HTTP 403)';
        }
        if (updateRes.status !== 200) {
            throw new Error('HTTP_UPDATE_MSG ' + updateRes.status + ': ' + JSON.stringify(updateRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const receivedContent = safeGet(payload, 'content') || '?';

        if (receivedContent !== newContent) {
            throw new Error('CONTENT_MISMATCH — esperado "' + newContent + '", recibido "' + receivedContent + '"');
        }

        return 'content="' + receivedContent + '"';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 9: DmMessageUpdated — cubre array manual en private-dm.*
    // ═══════════════════════════════════════════════════════════
    await runTest('9. DmMessageUpdated', async () => {
        const chName = 'private-dm-conversation.' + CFG.DM_UUID;
        const ch = await subscribe(pusher, chName);

        // Crear DM temporal para luego actualizar
        const dmClientUuid = uuidv4();
        const dmCreateRes = await httpRequest(
            'POST', '/api/dm-conversations/' + CFG.DM_UUID + '/messages', token,
            { client_uuid: dmClientUuid, content: 'WSS DM to update ' + Date.now() },
        );
        if (dmCreateRes.status !== 201) {
            throw new Error('HTTP_CREATE_DM ' + dmCreateRes.status + ': ' + JSON.stringify(dmCreateRes.body).substring(0, 200));
        }
        const dmUuid = safeGet(dmCreateRes.body, 'data.uuid');
        if (!dmUuid) throw new Error('No se pudo extraer uuid del DM creado');
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'DmMessageUpdated');

        const newContent = 'WSS DM updated ' + Date.now();
        const updateRes = await httpRequest(
            'PATCH', '/api/dm-conversations/' + CFG.DM_UUID + '/messages/' + dmUuid, token,
            { content: newContent },
        );
        if (updateRes.status === 403) {
            return 'SKIP — tester no es el sender del DM (HTTP 403)';
        }
        if (updateRes.status !== 200) {
            throw new Error('HTTP_UPDATE_DM ' + updateRes.status + ': ' + JSON.stringify(updateRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const receivedContent = safeGet(payload, 'content') || '?';

        if (receivedContent !== newContent) {
            throw new Error('CONTENT_MISMATCH — esperado "' + newContent + '", recibido "' + receivedContent + '"');
        }

        return 'content="' + receivedContent + '"';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 10: FriendshipStatusChanged — cubre 2 canales + private-user.*
    // ═══════════════════════════════════════════════════════════
    await runTest('10. FriendshipStatusChanged', async () => {
        const chName = 'private-user.' + CFG.USER_UUID;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'FriendshipStatusChanged');

        const clientUuid = uuidv4();
        const createRes = await httpRequest(
            'POST', '/api/user/friend-requests', token,
            { client_uuid: clientUuid, receiver_uuid: CFG.OTHER_USER_UUID },
        );

        if (createRes.status === 200) {
            return 'SKIP — friend request ya existe (HTTP 200), evento no se dispara';
        }
        if (createRes.status !== 201) {
            throw new Error('HTTP ' + createRes.status + ' (esperado 201): ' + JSON.stringify(createRes.body).substring(0, 200));
        }

        const eventData = await eventPromise;
        const payload = eventData.d || eventData;
        const action = payload.action || '?';
        const sender = payload.sender_uuid || '?';
        const receiver = payload.receiver_uuid || '?';

        if (action !== 'created') {
            throw new Error('ACTION_MISMATCH — esperado "created", recibido "' + action + '"');
        }
        if (sender !== CFG.USER_UUID) {
            throw new Error('SENDER_MISMATCH — esperado ' + CFG.USER_UUID + ', recibido ' + sender);
        }
        if (receiver !== CFG.OTHER_USER_UUID) {
            throw new Error('RECEIVER_MISMATCH');
        }

        return 'action=' + action + ' sender=' + sender.substring(0, 8) + '...';
    });

    // ═══════════════════════════════════════════════════════════
    // TEST 11: ClubDeleted — cubre array delete + club temporal
    // ═══════════════════════════════════════════════════════════
    await runTest('11. ClubDeleted', async () => {
        // Usar el club temporal creado en test 6
        const clubToDelete = tempClubUuid;
        if (!clubToDelete) {
            return 'SKIP — test 6 no creo un club temporal';
        }

        const chName = 'private-club.' + clubToDelete;
        const ch = await subscribe(pusher, chName);
        log('SUB', chName);

        const eventPromise = waitForEvent(ch, 'ClubDeleted');

        const deleteRes = await httpRequest(
            'DELETE', '/api/clubs/' + clubToDelete, token, null,
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
        if (payloadUuid !== clubToDelete) {
            throw new Error('UUID_MISMATCH — esperado ' + clubToDelete + ', recibido ' + payloadUuid);
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
