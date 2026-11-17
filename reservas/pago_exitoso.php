<?php
require_once __DIR__ . '/config.php';

function stripe_get(string $endpoint, string $secret): array {
    $ch = curl_init("https://api.stripe.com/v1/{$endpoint}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => "{$secret}:",
        CURLOPT_TIMEOUT        => 15,
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result ?: '{}', true) ?? [];
}

$session_id = $_GET['session_id'] ?? '';
$res_id     = preg_replace('/[^A-Z0-9\-]/', '', strtoupper($_GET['res_id'] ?? ''));

$reserva  = null;
$pagado   = false;
$error    = '';

if ($res_id) {
    try {
        $stmt = getDB()->prepare("SELECT * FROM reservas WHERE id = ?");
        $stmt->execute([$res_id]);
        $reserva = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Error de base de datos.';
    }
}

if ($reserva && $session_id && !$reserva['pagado']) {
    // Verify with Stripe
    $sess = stripe_get('checkout/sessions/' . urlencode($session_id), STRIPE_SECRET_KEY);
    if (!empty($sess['payment_status']) && $sess['payment_status'] === 'paid') {
        try {
            getDB()->prepare("UPDATE reservas SET pagado = 1, stripe_session_id = ?, estado = 'confirmada' WHERE id = ?")
                   ->execute([$session_id, $res_id]);
            $reserva['pagado']            = 1;
            $reserva['stripe_session_id'] = $session_id;
            $reserva['estado']            = 'confirmada';
            $pagado = true;
            // Send WhatsApp confirmation after successful payment
            require_once __DIR__ . '/enviar_whatsapp.php';
            enviarWhatsappReserva($reserva);
        } catch (PDOException $e) {
            $error = 'No se pudo actualizar la reserva.';
        }
    }
} elseif ($reserva && $reserva['pagado']) {
    $pagado = true;
}

$nombre_tour = htmlspecialchars($reserva['tipo_tour'] ?? '', ENT_QUOTES, 'UTF-8');
$fecha_fmt   = $reserva ? date('d/m/Y', strtotime($reserva['fecha'])) : '';
$hora_fmt    = $reserva ? substr($reserva['hora'], 0, 5) : '';
$monto_fmt   = $reserva && $reserva['monto'] > 0
    ? number_format((float)$reserva['monto'], 2, ',', '.') . ' €'
    : 'A consultar';
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $pagado ? '¡Pago confirmado!' : 'Resultado del pago' ?> — TukTuk Norris</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root { --naranja: #f0a500; }
        body { background: #f5f6fa; font-family: 'Segoe UI', sans-serif; }
        .pago_wrap { max-width: 600px; margin: 60px auto; padding: 20px; }
        .pago_card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,.08); padding: 40px; text-align: center; }
        .pago_icon { font-size: 64px; margin-bottom: 20px; }
        .pago_card h1 { font-size: 26px; font-weight: 800; color: #1a1a2e; margin-bottom: 8px; }
        .pago_card .sub { color: #888; margin-bottom: 28px; }
        .detalle { background: #f9f9fb; border-radius: 10px; padding: 20px; text-align: left; margin-bottom: 24px; }
        .detalle_row { display: flex; justify-content: space-between; padding: 7px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .detalle_row:last-child { border-bottom: none; }
        .detalle_row .lbl { color: #888; }
        .detalle_row .val { font-weight: 700; color: #1a1a2e; }
        .total_row .val { color: var(--naranja); font-size: 18px; }
        .btn_pdf { display: inline-block; background: var(--naranja); color: #fff; text-decoration: none; border-radius: 8px; padding: 13px 28px; font-weight: 800; font-size: 15px; margin-bottom: 12px; }
        .btn_pdf:hover { background: #d4900a; color: #fff; }
        .btn_home { display: inline-block; color: #888; text-decoration: none; font-size: 13px; }
        .err_card { background: #fff3f3; border: 1.5px solid #f5c6cb; color: #721c24; border-radius: 10px; padding: 20px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="pago_wrap">
    <div style="text-align:center;margin-bottom:28px;">
        <a href="../index.html">
            <img src="../assets/images/logo-tuktuk.svg" alt="TukTuk Norris" style="height:56px;width:auto;">
        </a>
    </div>

    <?php if ($error): ?>
    <div class="err_card"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="pago_card">
        <?php if ($pagado && $reserva): ?>
        <div class="pago_icon">✅</div>
        <h1>¡Pago confirmado!</h1>
        <p class="sub">Tu reserva está confirmada. Te esperamos en Málaga 🛺</p>

        <div class="detalle">
            <div class="detalle_row">
                <span class="lbl">Referencia</span>
                <span class="val"><?= htmlspecialchars($reserva['id']) ?></span>
            </div>
            <div class="detalle_row">
                <span class="lbl">Tour</span>
                <span class="val"><?= $nombre_tour ?></span>
            </div>
            <div class="detalle_row">
                <span class="lbl">Fecha</span>
                <span class="val"><?= $fecha_fmt ?> a las <?= $hora_fmt ?> h</span>
            </div>
            <div class="detalle_row">
                <span class="lbl">Personas</span>
                <span class="val"><?= (int)$reserva['personas'] ?></span>
            </div>
            <div class="detalle_row total_row">
                <span class="lbl">Total pagado</span>
                <span class="val"><?= $monto_fmt ?></span>
            </div>
        </div>

        <a href="descargar_pdf.php?res_id=<?= urlencode($reserva['id']) ?>" class="btn_pdf">
            📄 Descargar confirmación PDF
        </a><br>
        <a href="../index.html" class="btn_home">← Volver al inicio</a>

        <?php elseif ($reserva && !$pagado): ?>
        <div class="pago_icon">⏳</div>
        <h1>Pago pendiente</h1>
        <p class="sub">No pudimos confirmar el pago. Si el cargo apareció en tu tarjeta, contáctanos.</p>
        <div class="detalle">
            <div class="detalle_row">
                <span class="lbl">Referencia</span>
                <span class="val"><?= htmlspecialchars($reserva['id']) ?></span>
            </div>
        </div>
        <a href="../reservar.html" class="btn_pdf" style="background:#666;">← Intentar de nuevo</a>

        <?php else: ?>
        <div class="pago_icon">❌</div>
        <h1>Reserva no encontrada</h1>
        <p class="sub">No encontramos tu reserva. Por favor contáctanos.</p>
        <a href="../index.html" class="btn_home">← Volver al inicio</a>
        <?php endif; ?>
    </div>

    <p style="text-align:center;color:#ccc;font-size:12px;margin-top:20px;">
        TukTuk Norris · tuktuknorris.es · Málaga, España
    </p>
</div>
</body>
</html>
