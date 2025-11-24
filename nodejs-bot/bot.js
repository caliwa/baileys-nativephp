import { makeWASocket, useMultiFileAuthState, DisconnectReason } from '@whiskeysockets/baileys';
import sqlite3 from 'sqlite3';
import path from 'path';
import { fileURLToPath } from 'url';
import pino from 'pino';
import fs from 'fs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const dbPath = path.resolve(__dirname, '../database/database.sqlite');
const AUTH_FOLDER = path.resolve(__dirname, '../auth_info_baileys');

// --- BASE DE DATOS ---
function dbRun(sql, params = []) {
    const db = new sqlite3.Database(dbPath);
    db.run(sql, params, (err) => { if (err) console.error('[DB Error]', err.message); db.close(); });
}

function updateStatus(status, qr = null) {
    // CAMBIO CLAVE: Usamos INSERT OR REPLACE para asegurar que la fila ID 1 siempre exista
    // Esto arregla el problema de que se quede en "APAGADO" aunque haya QR
    const sql = `INSERT OR REPLACE INTO whatsapp_status (id, session_id, status, qr_code, updated_at, created_at) VALUES (1, 'default', ?, ?, datetime('now'), datetime('now'))`;
    dbRun(sql, [status, qr]);
}

function addLog(level, message, phone = null) {
    console.log(`[${level}] ${message}`);
    dbRun(`INSERT INTO whatsapp_logs (level, message, phone, created_at, updated_at) VALUES (?, ?, ?, datetime('now'), datetime('now'))`, [level, message, phone]);
}

// --- LIMPIEZA ---
async function clearSessionAndRestart() {
    addLog('WARN', '♻️ Sesión corrupta detectada. Limpiando...');
    try {
        if (fs.existsSync(AUTH_FOLDER)) {
            fs.rmSync(AUTH_FOLDER, { recursive: true, force: true });
        }
        updateStatus('DISCONNECTED', null);
        addLog('INFO', '🔄 Reiniciando...');
        startBot();
    } catch (e) {
        addLog('ERROR', '❌ Fallo al limpiar: ' + e.message);
    }
}

// --- BOT ---
async function startBot() {
    if (!fs.existsSync(AUTH_FOLDER)) {
        addLog('INFO', '✨ Iniciando sesión NUEVA.');
        // Forzamos la creación del registro en BD inmediatamente
        updateStatus('CONNECTING', null);
    } else {
        addLog('INFO', '📂 Cargando sesión existente...');
    }

    const { state, saveCreds } = await useMultiFileAuthState(AUTH_FOLDER);

    const sock = makeWASocket({
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: ["Native Hub", "Chrome", "1.0.0"],
        connectTimeoutMs: 60000,
        defaultQueryTimeoutMs: 60000,
    });

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            // Aquí es donde fallaba antes. Ahora forzará la escritura en BD.
            updateStatus('CONNECTING', qr);
            addLog('INFO', '⚡ CÓDIGO QR GENERADO. ESCANÉALO AHORA.');
        }

        if (connection === 'open') {
            updateStatus('CONNECTED', null);
            addLog('SUCCESS', '✅ Conexión establecida con WhatsApp.');
        }

        if (connection === 'close') {
            const code = (lastDisconnect.error)?.output?.statusCode;
            const shouldReconnect = code !== DisconnectReason.loggedOut;

            if (code === 401 || code === 403 || code === 440) {
                await clearSessionAndRestart();
            } else if (shouldReconnect) {
                addLog('WARN', `⚠️ Reconectando (${code})...`);
                startBot();
            } else {
                updateStatus('DISCONNECTED', null);
                addLog('ERROR', `❌ Desconectado (${code}).`);
            }
        }
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type === 'notify') {
            for (const msg of messages) {
                if (!msg.key.fromMe) {
                    const phone = msg.key.remoteJid.replace('@s.whatsapp.net', '');
                    const text = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '[Media]';
                    addLog('MESSAGE_IN', text, phone);
                }
            }
        }
    });
}

addLog('SYSTEM', '🚀 Motor Iniciado (Fix DB).');
startBot();