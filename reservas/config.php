<?php
// ══════════════════════════════════════════════════════════════
//  TukTuk Norris — Configuración de base de datos
//  Edita estos valores con los datos de tu Plesk
// ══════════════════════════════════════════════════════════════

define('DB_HOST',    '127.0.0.1');
define('DB_NAME',    'tuktuk_norris');
define('DB_USER',    'tuktusr');
define('DB_PASS',    '6OMP4c5hsdb?jpo@');
define('DB_CHARSET', 'utf8mb4');

// ── Contraseña del panel admin ─────────────────────────────────
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'tuktuk2024');

// ── Zona horaria ───────────────────────────────────────────────
date_default_timezone_set('Europe/Madrid');

// ── Producción segura ──────────────────────────────────────────
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);

// ── API keys: lee desde api_keys.json si existe (configurado desde el admin) ──
$_ttn_api_file = __DIR__ . '/api_keys.json';
$_ttn_ak = file_exists($_ttn_api_file) ? (json_decode(file_get_contents($_ttn_api_file), true) ?: []) : [];

define('STRIPE_PUBLIC_KEY',    !empty($_ttn_ak['stripe_public'])  ? $_ttn_ak['stripe_public']  : 'pk_test_REPLACE_WITH_YOUR_KEY');
define('STRIPE_SECRET_KEY',    !empty($_ttn_ak['stripe_secret'])  ? $_ttn_ak['stripe_secret']  : 'sk_test_REPLACE_WITH_YOUR_KEY');
define('STRIPE_WEBHOOK_SECRET',!empty($_ttn_ak['stripe_webhook']) ? $_ttn_ak['stripe_webhook'] : 'whsec_REPLACE_WITH_YOUR_WEBHOOK_SECRET');
define('SITE_URL',             !empty($_ttn_ak['site_url'])       ? $_ttn_ak['site_url']       : 'https://www.tuktuknorris.es');
define('TWILIO_ACCOUNT_SID',   !empty($_ttn_ak['twilio_sid'])     ? $_ttn_ak['twilio_sid']     : 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
define('TWILIO_AUTH_TOKEN',    !empty($_ttn_ak['twilio_token'])   ? $_ttn_ak['twilio_token']   : 'your_auth_token_here');
define('TWILIO_WHATSAPP_FROM', !empty($_ttn_ak['twilio_from'])    ? $_ttn_ak['twilio_from']    : '+14155238886');
unset($_ttn_api_file, $_ttn_ak);

// ── Conexión PDO (no tocar) ────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
