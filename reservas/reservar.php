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

// Load tours from tours_data.json (primary) or DB fallback
$tours_data_file = __DIR__ . '/tours_data.json';
$tours_full = [];
if (file_exists($tours_data_file)) {
    $td = json_decode(file_get_contents($tours_data_file), true);
    if (is_array($td)) $tours_full = array_filter($td, fn($t) => !empty($t['activo']));
}
if (!empty($tours_full)) {
    $tours_validos = array_column(array_values($tours_full), 'nombre');
} else {
    try {
        $tours_validos = getDB()
            ->query("SELECT tour_nombre FROM precios WHERE activo = 1")
            ->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $tours_validos = ['Tour Centro Histórico','Tour Playa y Puerto','Tour Ruta Picasso'];
    }
}

// Calculate monto (precio per person * personas)
$monto = 0.00;
foreach ($tours_full as $t) {
    if ($t['nombre'] === $tipo_tour) {
        $monto = round((float)$t['precio'] * $personas, 2);
        break;
    }
}
// Fallback: try precios table
if ($monto === 0.00 && !empty($tipo_tour)) {
    try {
        $row = getDB()->prepare("SELECT precio_base FROM precios WHERE tour_nombre = ? AND activo = 1");
        $row->execute([$tipo_tour]);
        $p = $row->fetchColumn();
        if ($p !== false) $monto = round((float)$p * $personas, 2);
    } catch (PDOException $e) {}
}

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
            (id, nombre, email, telefono, fecha, hora, personas, monto, stripe_session_id, pagado, tipo_tour, idioma, mensaje, estado, created_at)
        VALUES
            (:id, :nombre, :email, :telefono, :fecha, :hora, :personas, :monto, NULL, 0, :tipo_tour, :idioma, :mensaje, 'pendiente', NOW())
    ");
    $stmt->execute([
        ':id'        => $id,
        ':nombre'    => $nombre,
        ':email'     => $email,
        ':telefono'  => $telefono,
        ':fecha'     => $fecha,
        ':hora'      => $hora,
        ':personas'  => $personas,
        ':monto'     => $monto,
        ':tipo_tour' => $tipo_tour,
        ':idioma'    => $idioma,
        ':mensaje'   => $mensaje,
    ]);

    echo json_encode([
        'success' => true,
        'message' => '¡Reserva registrada! Confirma ahora con el pago.',
        'id'      => $id,
        'monto'   => $monto,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al guardar la reserva. Por favor llámanos al +34 951 234 567.',
    ]);
}
