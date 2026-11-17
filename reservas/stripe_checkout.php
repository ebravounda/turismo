<?php
require_once __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');

function stripe_request(string $endpoint, array $params, string $secret): array {
    $ch = curl_init("https://api.stripe.com/v1/{$endpoint}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_USERPWD        => "{$secret}:",
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result ?: '{}', true) ?? [];
}

$res_id = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['res_id'] ?? ''));
$monto  = round((float)($_GET['monto'] ?? 0), 2);

if (empty($res_id) || $monto <= 0) {
    http_response_code(400);
    die('Parámetros inválidos.');
}

// Verify reservation exists
try {
    $db  = getDB();
    $row = $db->prepare("SELECT id, nombre, tipo_tour, pagado FROM reservas WHERE id = ?");
    $row->execute([$res_id]);
    $reserva = $row->fetch();
} catch (PDOException $e) {
    http_response_code(500);
    die('Error de base de datos.');
}

if (!$reserva) {
    http_response_code(404);
    die('Reserva no encontrada.');
}

if ($reserva['pagado']) {
    header('Location: ' . SITE_URL . '/reservas/pago_exitoso.php?res_id=' . urlencode($res_id));
    exit;
}

$monto_centimos = (int)round($monto * 100);
$nombre_tour    = htmlspecialchars($reserva['tipo_tour'], ENT_QUOTES, 'UTF-8');
$success_url    = SITE_URL . '/reservas/pago_exitoso.php?session_id={CHECKOUT_SESSION_ID}&res_id=' . urlencode($res_id);
$cancel_url     = SITE_URL . '/reservar.html';

$response = stripe_request('checkout/sessions', [
    'payment_method_types[]'                              => 'card',
    'line_items[0][price_data][currency]'                 => 'eur',
    'line_items[0][price_data][unit_amount]'              => $monto_centimos,
    'line_items[0][price_data][product_data][name]'       => 'TukTuk Norris — ' . $nombre_tour,
    'line_items[0][price_data][product_data][description]'=> 'Referencia de reserva: ' . $res_id,
    'line_items[0][quantity]'                             => 1,
    'mode'                                                => 'payment',
    'customer_email'                                      => '',
    'success_url'                                         => $success_url,
    'cancel_url'                                          => $cancel_url,
    'metadata[res_id]'                                    => $res_id,
], STRIPE_SECRET_KEY);

if (!empty($response['url'])) {
    // Store the session id pending
    try {
        $db->prepare("UPDATE reservas SET stripe_session_id = ? WHERE id = ?")
           ->execute([$response['id'], $res_id]);
    } catch (PDOException $e) {}
    header('Location: ' . $response['url']);
    exit;
}

// Stripe error — show helpful message
http_response_code(502);
?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Error de pago</title>
<style>body{font-family:sans-serif;text-align:center;padding:60px;color:#333}
a{color:#f0a500}</style>
</head>
<body>
<h2>No se pudo iniciar el pago</h2>
<p>Por favor inténtalo de nuevo o <a href="<?= SITE_URL ?>/reservar.html">vuelve a la reserva</a>.</p>
<?php if (!empty($response['error']['message'])): ?>
<p style="color:#999;font-size:13px"><?= htmlspecialchars($response['error']['message']) ?></p>
<?php endif; ?>
</body></html>
