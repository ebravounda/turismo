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
    // ── Hero ──────────────────────────────────────────
    'hero_subtitle'    => 'Descubre Málaga de una forma única',
    'hero_title'       => '¿Listo para tu aventura en tuk-tuk?',
    // ── Nuestros Tours ────────────────────────────────
    'tours_heading'    => 'Nuestros Tours',
    'tours_subtext'    => 'Descubre los barrios y rincones más emblemáticos de Málaga a bordo de nuestros tuk-tuks.',
    'dest1_title'      => 'Casco Antiguo',
    'dest2_title'      => 'La Malagueta',
    'dest3_title'      => 'El Soho',
    'dest4_title'      => 'Pedregalejo',
    'dest5_title'      => 'Teatinos',
    'dest6_title'      => 'El Palo',
    'dest7_title'      => 'Muelle Uno',
    'dest8_title'      => 'La Victoria',
    // ── Explora Málaga (servicios) ────────────────────
    'srv_heading'      => 'Explora Málaga en Tuk-Tuk',
    'srv_subtext'      => 'Sube a nuestros tuk-tuks y descubre los rincones más emblemáticos de Málaga. Guías locales apasionados, rutas únicas y diversión asegurada para toda la familia.',
    'srv1_title'       => 'Centro Histórico',
    'srv2_title'       => 'Ruta del Picasso',
    'srv3_title'       => 'Playa y Puerto',
    'srv4_title'       => 'Tour Atardecer',
    // ── Por qué Elegirnos ─────────────────────────────
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
    // ── Galería ───────────────────────────────────────
    'gallery_heading'  => 'Galería de Tours',
    'gallery_subtext'  => 'Cada ruta es una historia distinta. Descubre Málaga desde otro ángulo — sus plazas, su luz, su gente — a bordo de nuestros tuk-tuks.',
    // ── Testimonios ───────────────────────────────────
    'testi_heading'    => 'Lo que dicen nuestros viajeros',
    'testi1_text'      => '"Fue una experiencia increíble. El conductor nos llevó por rincones de Málaga que nunca hubiéramos encontrado solos. ¡100% recomendable!"',
    'testi1_name'      => 'María G. — Madrid',
    'testi2_text'      => '"Reservamos el Tour Atardecer y fue mágico. Ver Málaga desde el puerto al caer el sol en un tuk-tuk es algo que no olvidaremos."',
    'testi2_name'      => 'James T. — Londres',
    'testi3_text'      => '"El guía fue muy amable y divertido, sabía todo sobre la historia de la ciudad. Una forma única y diferente de conocer Málaga."',
    'testi3_name'      => 'Sophie L. — París',
    // ── Equipo ────────────────────────────────────────
    'team_heading'     => 'Nuestro Equipo',
    'team_subtext'     => 'Somos un equipo apasionado por Málaga con más de 10 años de experiencia mostrando los rincones más auténticos de la ciudad a bordo de nuestros tuk-tuks eléctricos.',
    'guide1_name'      => 'Carlos Norris',
    'guide1_role'      => 'Fundador & Guía',
    'guide2_name'      => 'Elena Vega',
    'guide2_role'      => 'Guía Oficial',
    'guide3_name'      => 'Marcos Ruiz',
    'guide3_role'      => 'Copiloto & Fotógrafo',
    // ── Tours (producto) ──────────────────────────────
    'tour1_emoji'      => '🏛️',
    'tour1_nombre'     => 'Centro Histórico',
    'tour1_desc'       => 'Catedral, Alcazaba, calle Larios y los rincones más emblemáticos',
    'tour1_duracion'   => '90 min',
    'tour1_precio'     => 'Desde 15€',
    'tour2_emoji'      => '🏖️',
    'tour2_nombre'     => 'Playa y Puerto',
    'tour2_desc'       => 'Malagueta, Muelle Uno, Palmeral de las Sorpresas y paseo marítimo',
    'tour2_duracion'   => '60 min',
    'tour2_precio'     => 'Desde 12€',
    'tour3_emoji'      => '🎨',
    'tour3_nombre'     => 'Ruta Picasso',
    'tour3_desc'       => 'Museo Picasso, casa natal, barrio de la Trinidad y arte urbano',
    'tour3_duracion'   => '75 min',
    'tour3_precio'     => 'Desde 14€',
    // ── Footer ────────────────────────────────────────
    'footer_direccion' => 'Calle Larios 5, Centro Histórico, Málaga, España',
    'footer_telefono1' => '+34 951 234 567',
    'footer_telefono2' => '+34 600 123 456',
    'footer_email1'    => 'hola@tuktuknorris.es',
    'footer_email2'    => 'reservas@tuktuknorris.es',
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
