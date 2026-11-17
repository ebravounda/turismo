<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/settings.php';

/**
 * Send a WhatsApp confirmation message via Twilio to the customer.
 * Returns true on success, false if not configured or on error.
 */
function enviarWhatsappReserva(array $r): bool {
    // Skip if Twilio not configured
    if (!defined('TWILIO_ACCOUNT_SID') || TWILIO_ACCOUNT_SID === 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx') {
        return false;
    }

    // Normalize phone number
    $telefono = preg_replace('/\s+/', '', $r['telefono'] ?? '');
    if (empty($telefono)) return false;
    if ($telefono[0] !== '+') $telefono = '+' . $telefono;

    // Load settings for punto_encuentro
    $settings = getSettings();
    $punto    = $settings['punto_encuentro'] ?? 'Plaza de la Constitución, Málaga';

    $fechaFmt = !empty($r['fecha']) ? date('d/m/Y', strtotime($r['fecha'])) : '—';
    $horaFmt  = !empty($r['hora'])  ? substr($r['hora'], 0, 5) . ' h'        : '—';
    $personas = (int)($r['personas'] ?? 1);
    $pagado   = !empty($r['pagado']);
    $montoStr = $pagado && !empty($r['monto']) && $r['monto'] > 0
        ? number_format((float)$r['monto'], 2, ',', '.') . ' €'
        : 'A pagar el día del tour';

    $msg = "✅ *¡Reserva confirmada! TukTuk Norris* 🛺\n\n"
         . "Hola *{$r['nombre']}*, tu reserva está registrada.\n\n"
         . "🗺️ *Tour:* {$r['tipo_tour']}\n"
         . "📅 *Fecha:* {$fechaFmt}\n"
         . "🕐 *Hora:* {$horaFmt}\n"
         . "👥 *Personas:* {$personas}\n"
         . "💶 *Importe:* {$montoStr}\n"
         . "🔖 *Referencia:* {$r['id']}\n\n"
         . "📍 *Punto de encuentro:*\n{$punto}\n\n"
         . "⚠️ *Política de cancelación:*\n"
         . "• Más de 24 h de antelación: reembolso 100%\n"
         . "• Menos de 24 h: reembolso 50%\n\n"
         . "¿Tienes dudas? Responde a este mensaje o llámanos.\n"
         . "¡Hasta pronto en Málaga! 🌞";

    $url  = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_ACCOUNT_SID . '/Messages.json';
    $data = http_build_query([
        'From' => 'whatsapp:' . TWILIO_WHATSAPP_FROM,
        'To'   => 'whatsapp:' . $telefono,
        'Body' => $msg,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_USERPWD        => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        $json = json_decode($response, true);
        return !empty($json['sid']);
    }
    error_log("Twilio WhatsApp error {$httpCode}: {$response}");
    return false;
}
