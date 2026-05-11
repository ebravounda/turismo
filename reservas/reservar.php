<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// ── Sanitización ───────────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

$nombre    = clean($_POST['nombre']    ?? '');
$email_raw = trim($_POST['email']      ?? '');
$email     = filter_var($email_raw, FILTER_VALIDATE_EMAIL) ? $email_raw : '';
$telefono  = clean($_POST['telefono']  ?? '');
$fecha     = clean($_POST['fecha']     ?? '');
$hora      = clean($_POST['hora']      ?? '');
$personas  = max(1, min(20, (int)($_POST['personas'] ?? 1)));
$tipo_tour = clean($_POST['tipo_tour'] ?? '');
$idioma    = clean($_POST['idioma']    ?? 'Español');
$mensaje   = clean($_POST['mensaje']   ?? '');

$tours_validos = [
    'Tour Centro Histórico',
    'Tour Playa y Puerto',
    'Tour Ruta Picasso',
    'Tour Atardecer',
    'Tour Personalizado',
];

// ── Validación ─────────────────────────────────────────────────
$errors = [];
if (empty($nombre))                             $errors[] = 'El nombre es obligatorio';
if (empty($email))                              $errors[] = 'El email no es válido';
if (empty($telefono))                           $errors[] = 'El teléfono es obligatorio';
if (empty($fecha))                              $errors[] = 'La fecha es obligatoria';
if (empty($hora))                               $errors[] = 'La hora es obligatoria';
if (!in_array($tipo_tour, $tours_validos))      $errors[] = 'Tour no válido';
if (!empty($fecha) && strtotime($fecha) < strtotime('today')) {
    $errors[] = 'La fecha no puede ser en el pasado';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

// ── Generar ID único ───────────────────────────────────────────
$id = 'RES-' . strtoupper(substr(md5(uniqid('', true)), 0, 8));

// ── Guardar en MySQL ───────────────────────────────────────────
try {
    $db = getDB();
    $stmt = $db->prepare("
        INSERT INTO reservas
            (id, nombre, email, telefono, fecha, hora, personas, tipo_tour, idioma, mensaje, estado, created_at)
        VALUES
            (:id, :nombre, :email, :telefono, :fecha, :hora, :personas, :tipo_tour, :idioma, :mensaje, 'pendiente', NOW())
    ");
    $stmt->execute([
        ':id'        => $id,
        ':nombre'    => $nombre,
        ':email'     => $email,
        ':telefono'  => $telefono,
        ':fecha'     => $fecha,
        ':hora'      => $hora,
        ':personas'  => $personas,
        ':tipo_tour' => $tipo_tour,
        ':idioma'    => $idioma,
        ':mensaje'   => $mensaje,
    ]);

    echo json_encode([
        'success' => true,
        'message' => '¡Reserva realizada con éxito! Te contactaremos pronto para confirmar.',
        'id'      => $id,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la reserva. Por favor llámanos al +34 951 234 567.',
    ]);
}
