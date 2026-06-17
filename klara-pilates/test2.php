<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "PASO 1: PHP OK<br>";

$configFile = __DIR__ . '/data/config.json';
$raw = file_get_contents($configFile);
echo "PASO 2: config.json leido - bytes: " . strlen($raw) . "<br>";

$config = json_decode($raw, true);
echo "PASO 3: json_decode OK - tipo: " . gettype($config) . "<br>";

if (!is_array($config)) {
    echo "ERROR: config.json no es JSON valido. Contenido: " . htmlspecialchars($raw);
    exit;
}

echo "PASO 4: precios: " . print_r($config['precios'], true) . "<br>";
echo "PASO 5: horarios: " . implode(', ', $config['horarios']) . "<br>";

$reservasFile = __DIR__ . '/data/reservas.json';
$reservas = json_decode(file_get_contents($reservasFile), true);
echo "PASO 6: reservas.json OK - " . count($reservas) . " reservas<br>";

echo "<br><strong>Todo OK - el problema esta en el HTML/CSS/JS de reservar.php</strong>";
?>
