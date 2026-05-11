<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $db   = getDB();
    $rows = $db->query("SELECT tour_nombre, precio_base, duracion FROM precios WHERE activo = 1 ORDER BY id")
               ->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($rows as $row) {
        $result[] = [
            'nombre'   => $row['tour_nombre'],
            'precio'   => (float)$row['precio_base'],
            'duracion' => $row['duracion'],
        ];
    }
    echo json_encode($result);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([]);
}
