<?php
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$settings_file = __DIR__ . '/site_settings.json';

$defaults = [
    'punto_encuentro' => 'Calle Larios 5, Málaga',
    'telefono'        => '+34 951 234 567',
    'cancelacion'     => 'Gratuita hasta 24h antes',
    'whatsapp'        => '',
    'logo_size'       => 72,
];

function load_settings(string $file, array $defaults): array {
    if (!file_exists($file)) return $defaults;
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    echo json_encode(load_settings($settings_file, $defaults));
    exit;
}

if ($method === 'POST') {
    if (!isset($_SESSION['admin'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    $settings = load_settings($settings_file, $defaults);

    foreach (array_keys($defaults) as $key) {
        if (isset($_POST[$key])) {
            $settings[$key] = htmlspecialchars(strip_tags(trim($_POST[$key])), ENT_QUOTES, 'UTF-8');
        }
    }

    file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Método no permitido']);
