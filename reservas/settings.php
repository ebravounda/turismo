<?php
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$settings_file = __DIR__ . '/site_settings.json';

$defaults = [
    'punto_encuentro'  => 'Calle Larios 5, Málaga',
    'telefono'         => '+34 951 234 567',
    'cancelacion'      => 'Gratuita hasta 24h antes',
    'whatsapp'         => '',
    'logo_size'        => 72,
    // Textos del index
    'hero_subtitle'    => 'Descubre Málaga de una forma única',
    'hero_title'       => '¿Listo para tu aventura en tuk-tuk?',
    'features_heading' => 'Por qué Elegirnos',
    'features_subtext' => 'Más de 10 años recorriendo Málaga en tuk-tuk. Descubre lo que nos hace únicos y por qué nuestros viajeros repiten.',
    'feat1_title'      => 'Tours Exclusivos',
    'feat1_desc'       => 'Diseñamos cada tour a tu medida. Grupos pequeños, rutas personalizadas y atención cercana para que vivas Málaga como si fueras un local.',
    'feat2_title'      => 'Guías Apasionados',
    'feat2_desc'       => 'Nuestros guías conocen cada rincón de Málaga. Historias reales, anécdotas locales y una sonrisa garantizada durante todo el recorrido.',
    'feat3_title'      => 'Calidad Premium',
    'feat3_desc'       => 'Nuestros tuk-tuks están equipados y mantenidos para ofrecerte la mayor comodidad. Seguridad, puntualidad y una experiencia de primera clase.',
    'feat4_title'      => 'Reserva Segura',
    'feat4_desc'       => 'Pago 100% seguro y cancelación flexible. Tu reserva está protegida y nuestro equipo disponible para atenderte en cualquier momento.',
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
