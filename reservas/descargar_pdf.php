<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pdf_generator.php';

$res_id = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['res_id'] ?? ''));

if (empty($res_id)) {
    http_response_code(400);
    die('Parámetro res_id requerido.');
}

try {
    $stmt = getDB()->prepare("SELECT * FROM reservas WHERE id = ?");
    $stmt->execute([$res_id]);
    $r = $stmt->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    die('Error de base de datos.');
}

if (!$r) {
    http_response_code(404);
    die('Reserva no encontrada.');
}

$pdf = (new ReservaPDF())->generate($r);

$filename = 'reserva-' . $res_id . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store');
echo $pdf;
