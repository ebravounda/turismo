<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Directorio de datos (fuera del webroot en producción idealmente)
$data_dir = __DIR__ . '/data';
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0755, true);
}

// Proteger el directorio data
$htaccess = $data_dir . '/.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "Deny from all\n");
}

$file = $data_dir . '/reservations.json';

// Leer reservas existentes
$reservations = [];
if (file_exists($file)) {
    $content = file_get_contents($file);
    $reservations = json_decode($content, true) ?? [];
}

// Sanitizar inputs
function clean($val) {
    return htmlspecialchars(strip_tags(trim($val ?? '')), ENT_QUOTES, 'UTF-8');
}

$nombre   = clean($_POST['nombre'] ?? '');
$email    = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? trim($_POST['email']) : '';
$telefono = clean($_POST['telefono'] ?? '');
$fecha    = clean($_POST['fecha'] ?? '');
$hora     = clean($_POST['hora'] ?? '');
$personas = max(1, min(20, intval($_POST['personas'] ?? 1)));
$tipo_tour = clean($_POST['tipo_tour'] ?? '');
$idioma   = clean($_POST['idioma'] ?? 'Español');
$mensaje  = clean($_POST['mensaje'] ?? '');

// Validación obligatoria
$errors = [];
if (empty($nombre))    $errors[] = 'El nombre es obligatorio';
if (empty($email))     $errors[] = 'El email no es válido';
if (empty($telefono))  $errors[] = 'El teléfono es obligatorio';
if (empty($fecha))     $errors[] = 'La fecha es obligatoria';
if (empty($hora))      $errors[] = 'La hora es obligatoria';
if (empty($tipo_tour)) $errors[] = 'Debes seleccionar un tour';

// Validar que la fecha no sea pasada
if (!empty($fecha) && strtotime($fecha) < strtotime(date('Y-m-d'))) {
    $errors[] = 'La fecha no puede ser en el pasado';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode('. ', $errors)]);
    exit;
}

// Crear la reserva
$reservation = [
    'id'        => 'RES-' . strtoupper(substr(md5(uniqid()), 0, 8)),
    'nombre'    => $nombre,
    'email'     => $email,
    'telefono'  => $telefono,
    'fecha'     => $fecha,
    'hora'      => $hora,
    'personas'  => $personas,
    'tipo_tour' => $tipo_tour,
    'idioma'    => $idioma,
    'mensaje'   => $mensaje,
    'estado'    => 'pendiente',  // pendiente | confirmada | cancelada
    'created_at' => date('Y-m-d H:i:s'),
];

$reservations[] = $reservation;

// Guardar con lock para evitar escrituras concurrentes
$fp = fopen($file, 'c+');
if (flock($fp, LOCK_EX)) {
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($reservations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    flock($fp, LOCK_UN);
}
fclose($fp);

echo json_encode([
    'success' => true,
    'message' => '¡Reserva realizada con éxito!',
    'id'      => $reservation['id'],
]);
