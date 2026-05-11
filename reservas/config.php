<?php
// ══════════════════════════════════════════════════════════════
//  TukTuk Norris — Configuración de base de datos
//  Edita estos valores con los datos de tu Plesk
// ══════════════════════════════════════════════════════════════

define('DB_HOST',    'localhost');          // Normalmente localhost en Plesk
define('DB_NAME',    'tuktuk_norris');      // Nombre de la base de datos que creaste
define('DB_USER',    'tuktuk_user');        // Usuario MySQL de Plesk
define('DB_PASS',    'TU_CONTRASEÑA_AQUI'); // Contraseña del usuario MySQL
define('DB_CHARSET', 'utf8mb4');

// ── Contraseña del panel admin ─────────────────────────────────
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'tuktuk2024');         // Cámbiala antes de poner en producción

// ── Zona horaria ───────────────────────────────────────────────
date_default_timezone_set('Europe/Madrid');

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
