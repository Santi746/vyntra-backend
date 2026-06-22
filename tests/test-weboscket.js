const WebSocket = require('ws');

const WS_URL = 'ws://localhost:8080/app/3559175319fbab34ca59e2ac05bbc6a7';

const ws = new WebSocket(WS_URL);

ws.on('open', () => {
    console.log('✅ Conectado a Reverb');

    // Suscribirse a un canal privado (simulado)
    ws.send(JSON.stringify({
        event: 'pusher:subscribe',
        data: { channel: 'channel.test-uuid' }
    }));
});

ws.on('message', (data) => {
    const message = JSON.parse(data);
    console.log('📩 Mensaje recibido:', message);

    // Verificar formato {t, d}
    if (message.data && message.data.t && message.data.d) {
        console.log('✅ Formato {t, d} correcto');
        console.log('   t:', message.data.t);
        console.log('   d:', message.data.d);
    } else {
        console.log('❌ Formato incorrecto. Esperado: {t, d}');
    }
});

ws.on('error', (err) => {
    console.error('❌ Error:', err.message);
});

ws.on('close', () => {
    console.log('🔌 Conexión cerrada');
});

// Cerrar después de 30 segundos
setTimeout(() => ws.close(), 30000);