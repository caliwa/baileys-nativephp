import { makeWASocket, useMultiFileAuthState, DisconnectReason, fetchLatestBaileysVersion, Browsers } from '@whiskeysockets/baileys';
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
        updateStatus('CONNECTING', null);
    } else {
        addLog('INFO', '📂 Cargando sesión existente...');
    }

    const { state, saveCreds } = await useMultiFileAuthState(AUTH_FOLDER);
    const { version, isLatest } = await fetchLatestBaileysVersion();
    addLog('INFO', `📡 Usando versión de WA: v${version.join('.')}, isLatest: ${isLatest}`);

    const sock = makeWASocket({
        version,
        auth: state,
        printQRInTerminal: false,
        logger: pino({ level: 'silent' }),
        browser: ['Ubuntu', 'Chrome', '20.0.04'],
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
                    try {
                        const fs = await import('fs');
                        const path = await import('path');
                        fs.appendFileSync(path.resolve(__dirname, '../storage/logs/wa_messages.log'), JSON.stringify(msg, null, 2) + '\n\n');
                    } catch(e) {}
                    // Ignorar estados de WhatsApp (historias)
                    if (msg.key.remoteJid === 'status@broadcast') continue;

                    let sender = msg.key.remoteJid;
                    if (sender?.endsWith('@g.us') && msg.key.participant) {
                        sender = msg.key.participant;
                    }

                    // Prevención extrema: Nunca procesar mensajes propios
                    const myId = sock.user?.id?.split(':')[0];
                    if (myId && sender?.includes(myId)) continue;

                    let uniqueId = sender?.replace(/@(s\.whatsapp\.net|c\.us|g\.us|lid)/g, '');
                    if (uniqueId?.includes(':')) uniqueId = uniqueId.split(':')[0];

                    let realPhone = null;

                    // Intentar resolver @lid a número de teléfono real
                    if (sender?.endsWith('@lid')) {
                        try {
                            const pnJid = await sock.signalRepository.lidMapping.getPNForLID(sender);
                            if (pnJid) {
                                realPhone = pnJid.replace(/@(s\.whatsapp\.net|c\.us|g\.us|lid)/g, '');
                                if (realPhone.includes(':')) realPhone = realPhone.split(':')[0];
                            }
                        } catch (e) {}
                    } else {
                        // Si no era LID, el uniqueId ya es el teléfono real
                        realPhone = uniqueId;
                    }

                    let displayStr = `ID: ${uniqueId}`;
                    if (realPhone && realPhone !== uniqueId) {
                        displayStr += ` | Tel: +${realPhone}`;
                    } else if (realPhone) {
                        displayStr = `+${realPhone}`;
                    }

                    if (msg.pushName) {
                        displayStr = `${msg.pushName} (${displayStr})`;
                    } else {
                        displayStr = `(${displayStr})`;
                    }
                    
                    const text = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '[Media]';
                    addLog('MESSAGE_IN', text, displayStr);

                    // --- RESPUESTA AUTOMÁTICA ---
                    try {
                        let targetJid = msg.key.remoteJid;

                        // 1. Marcar mensaje como leído
                        await sock.readMessages([msg.key]);
                        
                        // 2. Simular que está escribiendo
                        await sock.sendPresenceUpdate('composing', targetJid);
                        await new Promise(resolve => setTimeout(resolve, 1500));
                        
                        // 3. Enviar mensaje puro al mismo chat (la forma más segura en Baileys)
                        await sock.sendMessage(targetJid, { text: 'TECNOLOGÍAS ALIMENTICIAS TRANSFORMACIÓN DIGITAL 2026' });
                        
                        // 4. Quitar el estado de escribiendo
                        await sock.sendPresenceUpdate('paused', targetJid);

                        addLog('INFO', `Respuesta enviada a ${targetJid}`);
                    } catch (error) {
                        addLog('ERROR', 'Error enviando respuesta: ' + error.message);
                    }
                }
            }
        }
    });
}

addLog('SYSTEM', '🚀 Motor Iniciado (Fix DB).');
startBot();