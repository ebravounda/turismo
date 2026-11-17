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

// ── Stripe (reemplaza con tus claves reales) ───────────────────
define('STRIPE_PUBLIC_KEY',    'pk_test_REPLACE_WITH_YOUR_KEY');
define('STRIPE_SECRET_KEY',    'sk_test_REPLACE_WITH_YOUR_KEY');
define('STRIPE_WEBHOOK_SECRET','whsec_REPLACE_WITH_YOUR_WEBHOOK_SECRET');
define('SITE_URL',             'https://www.tuktuknorris.es');

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
